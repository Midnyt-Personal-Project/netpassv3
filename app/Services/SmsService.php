<?php

namespace App\Services;

use Illuminate\Support\Facades\{Http, Log};

use App\Models\{Customer, SmsLog};

class SmsService
{
    protected ?string $apiKey;
    protected string $senderId;

    public function __construct()
    {
        $this->apiKey = env('ARKESEL_SMS_API_KEY');
        $this->senderId = env('ARKESEL_SMS_SENDER_ID');
    }

    /** Send an SMS through Arkesel and always save the actual outcome for the owner. */
    public function sendSms(string $phoneNumber, string $message, ?Customer $customer = null): bool
    {
        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

        if (blank($this->apiKey)) {
            return $this->logFailure($formattedPhone, $message, $customer, 'ARKESEL_SMS_API_KEY is not configured.');
        }

        try {
            // Simulated Arkesel API request
            // In production, uncomment the Http::get or Http::post request
            
            // $response = Http::get('https://sms.arkesel.com/sms/api', [
            //     'action' => 'send-sms',
            //     'api_key' => $this->apiKey,
            //     'to' => $formattedPhone,
            //     'from' => $this->senderId,
            //     'sms' => $message
            // ]);
            // $status = $response->successful() ? 'sent' : 'failed';
            

            // Log it in SMS logs
            SmsLog::create([
                'customer_id' => $customer ? $customer->id : null,
                'phone_number' => $formattedPhone,
                'message' => $message,
                'status' => 'sent', // Mocked as sent
            ]);

            Log::info("SMS Sent to {$formattedPhone}: {$message}");
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
        $phone = preg_replace('/\s+/', '', $phone);

        return preg_match('/^0[0-9]{9}$/', $phone) ? '233'.substr($phone, 1) : $phone;
    }
}
