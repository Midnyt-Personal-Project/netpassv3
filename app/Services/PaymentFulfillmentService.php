<?php

namespace App\Services;

use App\Jobs\SendOwnerSubscriptionEmail;
use App\Jobs\SendSubscriptionCredentialsSms;
use App\Models\Package;
use App\Models\Payment;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentFulfillmentService
{
    public function __construct(
        private readonly SubscriptionIssuer $subscriptionIssuer,
        private readonly ActivityLogger $activity,
    )
    {
    }

    /**
     * Fulfill a verified payment exactly once.
     *
     * @return array{payment: Payment, customer: \App\Models\Customer, newly_processed: bool}
     */
    public function fulfill(Payment $payment, ?string $fallbackPhone = null, ?string $ipAddress = null): array
    {
        [$payment, $customer, $newlyProcessed] = DB::transaction(function () use ($payment, $fallbackPhone): array {
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === 'success' && $lockedPayment->customer_id) {
                return [$lockedPayment, $lockedPayment->customer, false];
            }

            $phone = $lockedPayment->purchaser_phone ?: PhoneNumber::normalize($fallbackPhone);
            if (!$phone) {
                throw new RuntimeException('The payment has no valid purchaser phone number.');
            }

            $location = $lockedPayment->location()->firstOrFail();
            $package = Package::whereKey($lockedPayment->package_id)
                ->where('location_id', $location->id)
                ->firstOrFail();

            $customer = $this->subscriptionIssuer->issue(
                $location,
                $package,
                $phone,
                $lockedPayment->requested_mac_address,
                $lockedPayment->requested_device_name,
            );

            $lockedPayment->update([
                'customer_id' => $customer->id,
                'purchaser_phone' => $phone,
                'status' => 'success',
                'processed_at' => now(),
            ]);

            return [$lockedPayment->fresh(), $customer, true];
        }, 3);

        if ($newlyProcessed) {
            $location = $payment->location;

            // External gateways run in queue workers, so Paystack webhooks can
            // acknowledge the payment immediately after durable fulfillment.
            SendSubscriptionCredentialsSms::dispatch($payment->id);
            SendOwnerSubscriptionEmail::dispatch($payment->id);

            $this->activity->record(
                'payment.completed',
                "Online subscription {$payment->paystack_reference} completed for voucher {$customer->voucher_code} at {$location->name}.",
                null,
                $ipAddress,
            );
        }

        return [
            'payment' => $payment->setRelation('customer', $customer),
            'customer' => $customer,
            'newly_processed' => $newlyProcessed,
        ];
    }
}
