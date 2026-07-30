<?php

namespace App\Services;

use Illuminate\Support\Facades\{Http, Log};

use App\Models\{Customer, SmsLog};
use App\Support\PhoneNumber;

class SmsService
{
    protected ?string $apiKey;
    protected string $senderId;

    public function __construct()
    {
        $this->apiKey = config('services.arkesel.api_key');
        $this->senderId = config('services.arkesel.sender_id', 'OyaloWiFi');
    }

    /** Send an SMS through Arkesel and always save the actual outcome for the owner. */
    public function sendSms(string $phoneNumber, string $message, ?Customer $customer = null): bool
    {
        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

        if (blank($this->apiKey)) {
            return $this->logFailure($formattedPhone, $message, $customer, 'ARKESEL_SMS_API_KEY is not configured.');
        }

        try {
            $response = Http::timeout(15)->acceptJson()->get('https://sms.arkesel.com/sms/api', [
                'action' => 'send-sms',
                'api_key' => $this->apiKey,
                'to' => $formattedPhone,
                'from' => $this->senderId,
                'sms' => $message,
            ]);

            if (!$response->successful()) {
                return $this->logFailure($formattedPhone, $message, $customer, 'Gateway HTTP '.$response->status().': '.str($response->body())->limit(300));
            }

            SmsLog::create(['customer_id' => $customer?->id, 'phone_number' => $formattedPhone, 'message' => $message, 'status' => 'sent']);
            Log::info("SMS accepted by gateway for {$formattedPhone}.");

            return true;
        } catch (\Throwable $exception) {
            return $this->logFailure($formattedPhone, $message, $customer, $exception->getMessage());
        }
    }

    private function logFailure(string $phoneNumber, string $message, ?Customer $customer, string $reason): bool
    {
        Log::error("SMS delivery failed for {$phoneNumber}: {$reason}");
        SmsLog::create(['customer_id' => $customer?->id, 'phone_number' => $phoneNumber, 'message' => $message, 'status' => 'failed', 'error_message' => $reason]);

        return false;
    }

    public function sendCredentials(Customer $customer, string $packageName): bool
    {
        $expiryDate = $customer->expires_at ? $customer->expires_at->format('d M Y H:i') : 'Unlimited';
        $voucher = $customer->voucher_code ?: $customer->username;
        $message = "Welcome to Oyalo WiFi!\n\nYour WiFi voucher: {$voucher}\nUse this same voucher in both MikroTik username and password fields.\nPackage: {$packageName}\nExpires: {$expiryDate}\n\nEnjoy fast internet!";

        return $this->sendSms($customer->phone_number, $message, $customer);
    }

    public function sendExpiryNotification(Customer $customer): bool
    {
        return $this->sendSms($customer->phone_number, 'Hello, your Oyalo WiFi package has expired. Open your browser and connect to renew your internet access.', $customer);
    }

    protected function formatPhoneNumber(string $phone): string
    {
        return PhoneNumber::normalize($phone) ?? preg_replace('/\s+/', '', $phone) ?? $phone;
    }
}
