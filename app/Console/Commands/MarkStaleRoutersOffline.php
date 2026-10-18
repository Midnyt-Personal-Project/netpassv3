<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MarkStaleRoutersOffline extends Command
{
    protected $signature = 'routers:mark-offline
                            {--minutes= : Override the configured heartbeat timeout}
                            {--dry-run : Report stale routers without changing them}';

    protected $description = 'Mark online routers offline when their heartbeats are stale.';

    public function handle(ActivityLogger $activity): int
    {
        $minutes = (int) ($this->option('minutes') ?: config('services.router.offline_after_minutes', 3));
        $minutes = max(1, $minutes);
        $cutoff = now()->subMinutes($minutes);
        $dryRun = (bool) $this->option('dry-run');
        $offline = 0;

        Router::with('location.admin')
            ->where('status', 'online')
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('last_heartbeat')->orWhere('last_heartbeat', '<=', $cutoff);
            })
            ->orderBy('id')
            ->chunkById(100, function ($routers) use ($activity, $cutoff, $dryRun, $minutes, &$offline): void {
                foreach ($routers as $router) {
                    if ($dryRun) {
                        $this->line("Would mark {$router->router_id} offline.");
                        $offline++;
                        continue;
                    }

                    // Re-check the heartbeat in the update so a heartbeat that
                    // arrives during this command cannot be overwritten.
                    $updated = Router::whereKey($router->id)
                        ->where('status', 'online')
                        ->where(function ($query) use ($cutoff): void {
                            $query->whereNull('last_heartbeat')->orWhere('last_heartbeat', '<=', $cutoff);
                        })
                        ->update(['status' => 'offline']);

                    if (!$updated) {
                        continue;
                    }

                    // Email the owner before logging.
                    // $this->sendOfflineEmail($router, $minutes);

                    $activity->record(
                        'router.offline',
                        "Router {$router->router_id} was marked offline after more than {$minutes} minute(s) without a heartbeat.",
                    );
                    $offline++;
                }
            });

        $this->info($dryRun
            ? "{$offline} router(s) would be marked offline."
            : "{$offline} stale router(s) marked offline.");

        return self::SUCCESS;
    }

    protected function sendOfflineEmail(Router $router, int $minutes): void
    {
        $location = $router->location;
        $owner    = $location?->admin;

        if (!$owner || !$owner->email) {
            Log::warning('Router offline email skipped: no owner email for location', [
                'router_id'   => $router->id,
                'router_code' => $router->router_id,
                'location_id' => $router->location_id,
            ]);
            return;
        }

        $ownerName    = $owner->name ?? 'Admin';
        $locationName = $location->name ?? 'unknown location';
        $routerCode   = $router->router_id;
        $appName      = config('app.name');

        $body = "Hello {$ownerName},\n\n"
              . "Router {$routerCode} at {$locationName} has been offline for more than {$minutes} minute(s).\n\n"
              . "Please contact the person at the device location to check the Wi-Fi, power, or internet connection.\n\n"
              . "Thanks,\n{$appName}";

        try {
            Mail::raw($body, function ($message) use ($owner, $routerCode) {
                $message->to($owner->email)
                        ->subject("Router {$routerCode} is offline");
            });
        } catch (\Throwable $e) {
            Log::error('Router offline email failed', [
                'router_id'   => $router->id,
                'router_code' => $router->router_id,
                'to'          => $owner->email,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}