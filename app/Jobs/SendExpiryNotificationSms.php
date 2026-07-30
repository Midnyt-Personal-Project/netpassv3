<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SendExpiryNotificationSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $customerId)
    {
    }

    public function handle(SmsService $sms): void
    {
        $customer = Customer::find($this->customerId);

        if (!$customer || $customer->status !== 'expired') {
            return;
        }

        if (!$sms->sendExpiryNotification($customer)) {
            throw new RuntimeException("Expiry SMS delivery failed for voucher {$customer->voucher_code}.");
        }
    }
}
