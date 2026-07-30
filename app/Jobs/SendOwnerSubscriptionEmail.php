<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\OwnerNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SendOwnerSubscriptionEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $paymentId)
    {
    }

    public function handle(OwnerNotificationService $notifications): void
    {
        $payment = Payment::with(['location.admin', 'customer', 'package'])->find($this->paymentId);

        if (!$payment || $payment->status !== 'success' || !$payment->customer || !$payment->package) {
            return;
        }

        if (!$notifications->subscriptionCreated(
            $payment->location,
            $payment->customer,
            $payment->package,
            $payment,
        )) {
            throw new RuntimeException("Owner email delivery failed for payment {$payment->id}.");
        }
    }
}
