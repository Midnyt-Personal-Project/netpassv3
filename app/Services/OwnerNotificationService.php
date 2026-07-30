<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\Location;
use App\Models\Package;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OwnerNotificationService
{
    /** Send an optional sales notification and retain an auditable delivery result. */
    public function subscriptionCreated(Location $location, Customer $customer, Package $package, Payment $payment): bool
    {
        if (!$location->subscription_email_notifications || !$location->admin?->email) {
            return true;
        }

        $subject = "New Oyalo subscription — {$location->name}";
        $message = "A subscription has been created at {$location->name}.\n\n"
            . "Voucher: ".($customer->voucher_code ?: $customer->username)."\n"
            . "Phone: {$customer->phone_number}\n"
            . "Package: {$package->name}\n"
            . "Amount: GHS ".number_format((float) $payment->amount, 2)."\n"
            . "Expires: ".$customer->expires_at?->format('d M Y H:i')."\n"
            . "Reference: {$payment->paystack_reference}";

        try {
            Mail::raw($message, function ($mail) use ($location, $subject): void {
                $mail->to($location->admin->email)->subject($subject);
            });
            EmailLog::create(['location_id' => $location->id, 'customer_id' => $customer->id, 'payment_id' => $payment->id, 'to' => $location->admin->email, 'subject' => $subject, 'message' => $message, 'status' => 'sent']);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Owner subscription email could not be sent.', ['location_id' => $location->id, 'error' => $exception->getMessage()]);
            EmailLog::create(['location_id' => $location->id, 'customer_id' => $customer->id, 'payment_id' => $payment->id, 'to' => $location->admin->email, 'subject' => $subject, 'message' => $message, 'status' => 'failed', 'error_message' => $exception->getMessage()]);

            return false;
        }
    }
}
