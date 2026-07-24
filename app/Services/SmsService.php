<?php

namespace App\Services;

use Illuminate\Support\Facades\{Http, Log};

use App\Models\{Customer, SmsLog};

class SmsService
{
    protected $apiKey;
    protected $senderId;

    public function __construct()
    {
        $this->apiKey = env('ARKESEL_SMS_API_KEY');
        $this->senderId = env('ARKESEL_SMS_SENDER_ID');
    }

    /**
     * Send credentials and details to customer via Arkesel (or equivalent West African SMS Gateway).
     */
    public function sendSms(string $phoneNumber, string $message, ?Customer $customer = null)
    {
        // Sanitize phone number (e.g. convert 024... to +23324... or 23324...)
        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

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
        } catch (\Exception $e) {
            Log::error('SMS Service Exception: ' . $e->getMessage());
            SmsLog::create([
                'customer_id' => $customer ? $customer->id : null,
                'phone_number' => $formattedPhone,
                'message' => $message,
                'status' => 'failed',
            ]);
            return false;
        }
    }

    /**
     * Send credential details to hot-spot user.
     */
    public function sendCredentials(Customer $customer, string $packageName)
    {
        $expiryDate = $customer->expires_at ? $customer->expires_at->format('d M Y H:i') : 'Unlimited';
        $voucher = $customer->voucher_code ?: $customer->username;
        $message = "Welcome to Oyalo WiFi!\n\nYour WiFi voucher: {$voucher}\nUse this same voucher in both MikroTik username and password fields.\nPackage: {$packageName}\nExpires: {$expiryDate}\n\nEnjoy fast internet!";

        return $this->sendSms($customer->phone_number, $message, $customer);
    }

    /**
     * Send expiration warning.
     */
    public function sendExpiryNotification(Customer $customer)
    {
        $message = "Hello, your Oyalo WiFi package has expired. Open your browser and connect to renew your internet access.";
        return $this->sendSms($customer->phone_number, $message, $customer);
    }

    /**
     * Helper to format numbers for Ghana (+233 / 233)
     */
    protected function formatPhoneNumber(string $phone)
    {
        $phone = preg_replace('/\s+/', '', $phone);
        // If it starts with 0 and has 10 digits, replace with 233
        if (preg_match('/^0[0-9]{9}$/', $phone)) {
            return '233' . substr($phone, 1);
        }
        return $phone;
    }
}
