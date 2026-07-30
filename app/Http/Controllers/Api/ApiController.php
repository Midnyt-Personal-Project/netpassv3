<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Models\RouterCommand;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    /** Authenticate a router from its dedicated headers. Never accept credentials in query strings. */
    protected function authenticateRouter(Request $request): ?Router
    {
        $routerId = $request->header('X-Router-ID');
        $apiToken = $request->header('X-Router-Token');

        if (!is_string($routerId) || !is_string($apiToken) || $routerId === '' || $apiToken === '') {
            return null;
        }

        // Fetch by public router ID, then make the secret comparison timing-safe.
        $router = Router::where('router_id', $routerId)->first();

        return $router && hash_equals($router->api_token, $apiToken) ? $router : null;
    }

    public function heartbeat(Request $request)
    {
        $router = $this->authenticateRouter($request);
        if (!$router) {
            return response()->json(['error' => 'Unauthorized router'], 401);
        }

        $data = $request->validate(['model' => ['nullable', 'string', 'max:100']]);
        $wasOffline = $router->status !== 'online';
        $router->update(['last_heartbeat' => now(), 'status' => 'online', 'model' => $data['model'] ?? $router->model]);
        if ($wasOffline) {
            app(ActivityLogger::class)->record('router.online', "Router {$router->router_id} checked in and is online.", null, $request->ip());
        }

        return response()->json(['status' => 'success', 'message' => 'Heartbeat acknowledged', 'timestamp' => now()->toIso8601String()]);
    }

    public function fetchCommands(Request $request)
    {
        $router = $this->authenticateRouter($request);
        if (!$router) {
            return response()->json(['error' => 'Unauthorized router'], 401);
        }

        // A bounded batch avoids an oversized polling response if a router has been offline.
        $commands = $router->pendingCommands()->oldest()->limit(100)->get();

        return response()->json([
            'router_id' => $router->router_id,
            'commands' => $commands->map(fn (RouterCommand $command) => [
                'id' => $command->id,
                'type' => $command->command_type,
                'payload' => $command->payload,
            ])->values(),
        ]);
    }

    public function acknowledgeCommand(Request $request, int $commandId)
    {
        $router = $this->authenticateRouter($request);
        if (!$router) {
            return response()->json(['error' => 'Unauthorized router'], 401);
        }

        $data = $request->validate(['status' => ['required', 'in:completed,failed']]);
        $command = RouterCommand::whereKey($commandId)->where('router_id', $router->id)->first();
        if (!$command) {
            return response()->json(['error' => 'Command not found'], 404);
        }
        if ($command->status !== 'pending') {
            return response()->json(['error' => 'Command was already acknowledged'], 409);
        }

        $command->update(['status' => $data['status'], 'executed_at' => now()]);
        app(ActivityLogger::class)->record('router.command_acknowledged', "Router {$router->router_id} marked command {$command->id} ({$command->command_type}) as {$data['status']}.", null, $request->ip());

        return response()->json(['status' => 'success', 'message' => "Command {$commandId} status updated to {$data['status']}"]);
    }

    public function pullData(Request $request)
    {
        $router = $this->authenticateRouter($request);
        if (!$router) {
            return response()->json(['error' => 'Unauthorized router'], 401);
        }
        $commands = $router->pendingCommands()->oldest()->limit(100)->get();
        return response()->json([
            'router' => [
                'router_id' => $router->router_id,
                'name' => $router->name,
                'model' => $router->model,
                'status' => $router->status,
            ],
            'commands' => $commands->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->command_type,
                'payload' => $c->payload,
                'status' => $c->status,
            ])->values(),
        ]);
    }
}
