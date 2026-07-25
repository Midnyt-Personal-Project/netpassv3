<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\ActivityLogger;
use App\Services\MikroTikService;
use App\Services\SmsService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire {--dry-run : Report customers that would expire without changing anything}';
    protected $description = 'Expire overdue hotspot subscriptions and queue MikroTik access removal.';

    public function handle(MikroTikService $mikrotik, SmsService $sms, ActivityLogger $activity): int
    {
        $expired = 0;
        $dryRun = (bool) $this->option('dry-run');

        Customer::with(['location.routers', 'devices'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($customers) use ($mikrotik, $sms, $activity, $dryRun, &$expired): void {
                foreach ($customers as $customer) {
                    if ($dryRun) {
                        $this->line("Would expire {$customer->voucher_code} at {$customer->location->name}.");
                        $expired++;
                        continue;
                    }

                    // Conditional update prevents duplicate expiry work if two schedulers overlap.
                    $updated = Customer::whereKey($customer->id)->where('status', 'active')->update(['status' => 'expired']);
                    if (!$updated) {
                        continue;
                    }

                    $router = $customer->location->routers->firstWhere('status', 'online') ?? $customer->location->routers->first();
                    if ($router) {
                        $mikrotik->queueDisableUser($router, $customer);
                        // Registered devices must also lose bypass access when the voucher expires.
                        foreach ($customer->devices->where('status', 'active') as $device) {
                            $mikrotik->queueRemoveMac($router, $device);
                        }
                    }

                    $sms->sendExpiryNotification($customer);
                    $activity->record('subscription.expired', "Voucher {$customer->voucher_code} expired at {$customer->location->name}; router access removal was queued.");
                    $expired++;
                }
            });

        $this->info($dryRun ? "{$expired} subscription(s) would expire." : "{$expired} subscription(s) expired.");

        return self::SUCCESS;
    }
}
