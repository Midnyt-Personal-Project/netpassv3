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
                'user_agent' => $request->userAgent(),
            ]);
            return null;
        }

        $router = Router::where('router_id', $routerId)->first();

        if (!$router) {
            Log::warning('Router authentication failed: unknown router', [
                'router_id' => $routerId,
                'ip' => $request->ip(),
            ]);
            return null;
        }

        if (!hash_equals($router->api_token, $apiToken)) {
            Log::warning('Router authentication failed: invalid token', [
                'router_id' => $routerId,
                'ip' => $request->ip(),
            ]);
            return null;
        }

        return $router;
    }

    public function heartbeat(Request $request)
    {
        $router = $this->authenticateRouter($request);

        if (!$router) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized router',
            ], 401);
        }

        $data = $request->validate([
            'model' => ['nullable', 'string', 'max:100'],
            'identity' => ['nullable', 'string', 'max:100'],
            'version' => ['nullable', 'string', 'max:50'],
            'board' => ['nullable', 'string', 'max:100'],
            'uptime' => ['nullable', 'string', 'max:100'],
        ]);

        $wasOffline = $router->status !== 'online';

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
            Log::warning('Router fetchCommands: unauthorized router', [
                'ip' => $request->ip(),
                'router_id_header' => $request->header('X-Router-ID'),
                'router_token_header' => $request->header('X-Router-Token'),
                'all_headers' => $request->headers->all(),
                'query' => $request->query(),
            ]);
            return response()->json(['error' => 'Unauthorized router'], 401);
        }

        $commands = $router->pendingCommands()->oldest()->limit(100)->get();

        Log::debug('Router fetchCommands', [
            'router_id' => $router->router_id,
            'router_db_id' => $router->id,
            'pending_count' => $commands->count(),
            'types' => $commands->pluck('command_type')->unique()->values()->all(),
            'ip' => $request->ip(),
        ]);

        // REBOOT is fire-and-forget: /system reboot restarts the router
        // immediately, so it can never send an ACK back. Mark them completed
        // now to prevent them from being re-fetched (infinite reboot loop).
        $rebootCommands = $commands->where('command_type', 'REBOOT');
        foreach ($rebootCommands as $cmd) {
            $cmd->update(['status' => 'completed', 'executed_at' => now()]);
            $this->activityLogger->record(
                'router.command_acknowledged',
                "Router {$router->router_id} marked reboot command {$cmd->id} as completed (fire-and-forget reboot).",
                null,
                $request->ip()
            );
        }

        // Only non-reboot commands go into the script with ACK sections.
        $regularCommands = $commands->where('command_type', '!=', 'REBOOT');

        $baseUrl = rtrim($request->root(), '/');
        $script = "# OYALO SYNC\n";

        foreach ($regularCommands as $command) {
            $cmdScript = trim($command->script);
            if ($cmdScript === '') {
                continue;
            }

            $script .= "\n# ID {$command->id}\n" . $cmdScript . "\n";
        }

        // Append reboot commands (just /system reboot, no ACK expected).
        foreach ($rebootCommands as $command) {
            $script .= "\n# ID {$command->id}\n" . trim($command->script) . "\n";
        }

        if ($regularCommands->isNotEmpty()) {
            $script .= "\n# ACKNOWLEDGMENTS\n";
            foreach ($regularCommands as $command) {
                $script .= $this->buildAckBlock($command, $router, $baseUrl);
            }
        }

        return response(trim($script), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Build the ACK block for a single command.
     *
     * IMPORTANT: the fetch is emitted as ONE line. Multi-line backslash
     * continuations are fragile in RouterOS (trailing whitespace, CRLF line
     * endings, escaped backslashes, and Windows-edited .rsc files all break
     * them). Single-line avoids the entire class of problems.
     */
protected function buildAckBlock(RouterCommand $command, Router $router, string $baseUrl): string
{
    $id       = (int) $command->id;
    $ackUrl   = "{$baseUrl}/api/router/commands/{$id}/ack";
    $routerId = $this->escapeRosHeader($router->router_id);
    $token    = $this->escapeRosHeader($router->api_token);

    // All headers must be in ONE http-header-field string, comma-separated.
    $headers = "X-Router-ID: {$routerId},X-Router-Token: {$token},Content-Type: application/x-www-form-urlencoded";

    return "\n# ==========================================\n"
        . "# Oyalo ACK ID {$id}\n"
        . "# ==========================================\n"
        . ":log info \"OYALO ACK ID {$id} START\"\n"
        . ":do {\n"
        . "    /tool fetch url=\"{$ackUrl}\" http-method=post http-header-field=\"{$headers}\" http-data=\"status=completed\" output=none check-certificate=no\n"
        . "    :log info \"OYALO ACK ID {$id} DONE\"\n"
        . "} on-error={\n"
        . "    :log error \"OYALO ACK ID {$id} FETCH_FAILED\"\n"
        . "}\n"
        . ":log info \"OYALO ACK ID {$id} END\"\n";
}

    /**
     * Strip characters that would terminate/inject into the generated
     * RouterOS string literals (quotes, backslashes, CR/LF).
     */
    protected function escapeRosHeader(?string $value): string
    {
        return str_replace(['"', '\\', "\r", "\n"], '', (string) $value);
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
            'message' => "Command {$id} status updated to {$status}",
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