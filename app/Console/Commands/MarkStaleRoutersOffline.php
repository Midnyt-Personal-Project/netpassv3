<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

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

        Router::where('status', 'online')
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
}
