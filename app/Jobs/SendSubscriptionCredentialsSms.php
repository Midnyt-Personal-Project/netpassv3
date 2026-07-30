<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SendSubscriptionCredentialsSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $paymentId)
    {
    }

    public function handle(SmsService $sms): void
    {
        $payment = Payment::with(['customer', 'package'])->find($this->paymentId);

        if (!$payment || $payment->status !== 'success' || !$payment->customer || !$payment->package) {
            return;
        }

        if (!$sms->sendCredentials($payment->customer, $payment->package->name)) {
            throw new RuntimeException("Voucher SMS delivery failed for payment {$payment->id}.");
        }
    }
}
