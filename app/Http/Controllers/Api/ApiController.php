<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Models\RouterCommand;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ApiController extends Controller
{
    /**
     * Authenticate Router via Custom Headers
     */
    protected function authenticateRouter(Request $request)
    {
        $routerId = $request->header('X-Router-ID');
        $apiToken = $request->header('X-Router-Token');

        if (!$routerId || !$apiToken) {
            return null;
        }

        return Router::where('router_id', $routerId)
                     ->where('api_token', $apiToken)
                     ->first();
    }

    /**
     * Heartbeat endpoint - tells server the router is online.
     */
    public function heartbeat(Request $request)
    {
        $router = $this->authenticateRouter($request);

        if (!$router) {
            return response()->json(['error' => 'Unauthorized Router'], 401);
        }

        $router->update([
            'last_heartbeat' => Carbon::now(),
            'status' => 'online',
            'model' => $request->input('model', $router->model)
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Heartbeat acknowledged',
            'timestamp' => Carbon::now()->toIso8601String()
        ]);
    }

    /**
     * Polling endpoint - Router fetches pending commands.
     */
    public function fetchCommands(Request $request)
    {
        $router = $this->authenticateRouter($request);

        if (!$router) {
            return response()->json(['error' => 'Unauthorized Router'], 401);
        }

        // Fetch all pending commands
        $commands = $router->pendingCommands()->get();

        return response()->json([
            'router_id' => $router->router_id,
            'commands' => $commands->map(function ($cmd) {
                return [
                    'id' => $cmd->id,
                    'type' => $cmd->command_type,
                    'payload' => $cmd->payload,
                ];
            })
        ]);
    }

    /**
     * Router acknowledges command completion.
     */
    public function acknowledgeCommand(Request $request, $commandId)
    {
        $router = $this->authenticateRouter($request);

        if (!$router) {
            return response()->json(['error' => 'Unauthorized Router'], 401);
        }

        $command = RouterCommand::where('id', $commandId)
                                ->where('router_id', $router->id)
                                ->first();

        if (!$command) {
            return response()->json(['error' => 'Command not found'], 404);
        }

        $status = $request->input('status', 'completed'); // 'completed' or 'failed'
        $command->update([
            'status' => $status,
            'executed_at' => Carbon::now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Command {$commandId} status updated to {$status}"
        ]);
    }
}
