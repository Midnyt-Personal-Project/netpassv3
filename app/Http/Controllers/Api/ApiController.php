<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, Validator};

use App\Http\Controllers\Controller;
use App\Models\{Router, RouterCommand};
use App\Services\ActivityLogger;

class ApiController extends Controller
{
    protected ActivityLogger $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /** Authenticate a router from its dedicated headers. */
    protected function authenticateRouter(Request $request): ?Router
    {
        $routerId = $request->header('X-Router-ID');
        $apiToken = $request->header('X-Router-Token');

        if (!is_string($routerId) || !is_string($apiToken) || $routerId === '' || $apiToken === '') {
            Log::warning('Router authentication failed: missing credentials', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return null;
        }

        $router = Router::where('router_id', $routerId)->first();

        if (!$router) {
            Log::warning('Router authentication failed: unknown router', [
                'router_id' => $routerId,
                'ip' => $request->ip()
            ]);
            return null;
        }

        if (!hash_equals($router->api_token, $apiToken)) {
            Log::warning('Router authentication failed: invalid token', [
                'router_id' => $routerId,
                'ip' => $request->ip()
            ]);
            return null;
        }

        return $router;
    }

    public function heartbeat(Request $request)
{
    // Authenticate and identify the exact router from X-Router-ID + X-Router-Token
    $router = $this->authenticateRouter($request);

    if (!$router) {
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized router',
        ], 401);
    }

    // Validate heartbeat data
    $data = $request->validate([
        'model' => ['nullable', 'string', 'max:100'],
        'identity' => ['nullable', 'string', 'max:100'],
        'version' => ['nullable', 'string', 'max:50'],
        'board' => ['nullable', 'string', 'max:100'],
        'uptime' => ['nullable', 'string', 'max:100'],
    ]);

    $wasOffline = $router->status !== 'online';

    // Update THIS particular router
    $router->update([
        'status' => 'online',
        'last_heartbeat' => now(),
        'name' => $data['identity'] ?? $router->name,
        'model' => $data['model'] ?? $data['board'] ?? $router->model,
    ]);

    if ($wasOffline) {
        $this->activityLogger->record(
            'router.online',
            "Router {$router->router_id} checked in and is online.",
            null,
            $request->ip()
        );
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Heartbeat acknowledged',
        'router_id' => $router->router_id,
        'router_status' => $router->status,
        'last_heartbeat' => $router->last_heartbeat?->toIso8601String(),
        'timestamp' => now()->toIso8601String(),
    ]);
}

    public function fetchCommands(Request $request)
    {
        $router = $this->authenticateRouter($request);
        if (!$router) {
            return response()->json(['error' => 'Unauthorized router'], 401);
        }

        $commands = $router->pendingCommands()->oldest()->limit(100)->get();

        $baseUrl = rtrim($request->root(), '/');
        $script = "# OYALO SYNC\n";

        foreach ($commands as $command) {
            $cmdScript = trim($command->script);
            if ($cmdScript === '') {
                continue;
            }

            $script .= "\n# ID {$command->id}\n" . $cmdScript . "\n";
        }

        if ($commands->isNotEmpty()) {
            $script .= "\n# ACKNOWLEDGMENTS\n";
            foreach ($commands as $command) {
                $script .= "# ==========================================\n"
                    . "# Oyalo ACK ID {$command->id}\n"
                    . "# ==========================================\n"
                    . ":log info \"OYALO ACK ID {$command->id} START\"\n"
                    . ":local result{$command->id} [/tool fetch \\\n"
                    . "    url=\"{$baseUrl}/api/router/commands/{$command->id}/ack\" \\\n"
                    . "    http-method=post \\\n"
                    . "    http-header-field=\"X-Router-ID: {$router->router_id},X-Router-Token: {$router->api_token},Content-Type: application/x-www-form-urlencoded\" \\\n"
                    . "    http-data=\"status=completed\" \\\n"
                    . "    output=user \\\n"
                    . "    as-value \\\n"
                    . "    check-certificate=no]\n"
                    . ":log info \"OYALO ACK ID {$command->id} DONE\"\n"
                    . ":log info (\"STATUS = \".\$result{$command->id}->\"status\")\n"
                    . ":log info (\"RESPONSE = \".\$result{$command->id}->\"data\")\n"
                    . ":log info \"OYALO ACK ID {$command->id} END\"\n\n";
            }
        }

        return response(trim($script), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'no-store');
    }

    public function acknowledgeCommand(Request $request, int $id)
    {
        $router = $this->authenticateRouter($request);
        if (!$router) {
            return response()->json(['error' => 'Unauthorized router'], 401);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'in:completed,failed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid status'], 422);
        }

        $status = $request->input('status', 'completed');
        $command = RouterCommand::whereKey($id)->where('router_id', $router->id)->first();

        if (!$command) {
            return response()->json(['error' => 'Command not found'], 404);
        }

        // The conditional update makes acknowledgements safe when a router
        // retries while the first response is still in flight.
        $updated = RouterCommand::whereKey($command->id)
            ->where('router_id', $router->id)
            ->where('status', 'pending')
            ->update(['status' => $status, 'executed_at' => now()]);

        if (!$updated) {
            return response()->json(['error' => 'Command was already acknowledged'], 409);
        }

        $command->refresh();

        $this->activityLogger->record(
            'router.command_acknowledged',
            "Router {$router->router_id} marked command {$command->id} ({$command->command_type}) as {$status}.",
            null,
            $request->ip()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Command {$id} status updated to {$status}"
        ]);
    }

    public function manifest(Request $request)
    {
        $ref = $request->header('referer');
        $path = $ref ? parse_url($ref, PHP_URL_PATH) : '/';
        return response()->json([
            'name' => 'Oyalo Cloud Hotspot',
            'short_name' => 'Oyalo',
            'description' => 'Buy and manage hotspot access.',
            'start_url' => $path ?: '/',
            'display' => 'standalone',
            'background_color' => '#0f172a',
            'theme_color' => '#4f46e5',
            'scope' => '/',
        ]);
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
                'script' => $c->script,
                'payload' => $c->payload,
                'status' => $c->status,
            ])->values(),
        ]);
    }
}
