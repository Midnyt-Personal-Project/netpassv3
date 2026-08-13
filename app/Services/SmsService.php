<?php

namespace App\Services;

use Illuminate\Support\Facades\{Http, Log};

use App\Models\{Customer, SmsLog};

class SmsService
{
    protected ?string $apiKey;
    protected string $senderId;

    /** Human-readable explanations for Arkesel V1 error codes. */
    protected const ARKESEL_ERRORS = [
        '100' => 'Bad gateway request',
        '101' => 'Wrong action',
        '102' => 'Authentication failed — check ARKESEL_SMS_API_KEY',
        '103' => 'Invalid phone number',
        '104' => 'Phone coverage not active',
        '105' => 'Insufficient SMS balance — top up your Arkesel account',
        '106' => 'Invalid Sender ID — it must be an approved sender, max 11 characters',
        '109' => 'Invalid schedule time',
        '111' => 'Message contains a spam word — waiting for approval',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.arkesel.api_key');
        $this->senderId = config('services.arkesel.sender_id', 'OyaloWiFi');
    }

    /**
     * Send one SMS through Arkesel and always save the real outcome for the owner.
     *
     * The gateway answers HTTP 200 even when it rejects a message, so the JSON
     * body is checked too ({code: "ok"} means success, anything else failed).
     * If a local-format number is rejected, we retry once in international
     * format (or vice versa) before giving up.
     */
    public function sendSms(string $phoneNumber, string $message, ?Customer $customer = null, ?int $announcementId = null): bool
    {
        if (blank($this->apiKey)) {
            return $this->logFailure($phoneNumber, $message, $customer, $announcementId, 'ARKESEL_SMS_API_KEY is not configured in .env');
        }

        $attempts = $this->formatCandidates($phoneNumber);
        $lastReason = null;

        foreach ($attempts as $formatted) {
            [$ok, $reason] = $this->requestGateway($formatted, $message);

            if ($ok) {
                SmsLog::create([
                    'customer_id' => $customer?->id,
                    'announcement_id' => $announcementId,
                    'phone_number' => $formatted,
                    'message' => $message,
                    'status' => 'sent',
                ]);
                Log::info("SMS accepted by gateway for {$formatted}.");

                return true;
            }

            $lastReason = $reason;
            Log::warning("SMS attempt failed for {$formatted}: {$reason}");
        }

        return $this->logFailure($attempts[0] ?? $phoneNumber, $message, $customer, $announcementId, (string) $lastReason);
    }

    /**
     * Low-level gateway call. Returns [bool $ok, string $reason].
     */
    private function requestGateway(string $to, string $message): array
    {
        try {
            $response = Http::timeout(15)->get('https://sms.arkesel.com/sms/api', [
                'action' => 'send-sms',
                'api_key' => $this->apiKey,
                'to' => $to,
                'from' => $this->senderId,
                'sms' => $message,
            ]);

            $body = $response->body();

            if (!$response->successful()) {
                return [false, 'Gateway HTTP '.$response->status().': '.str($body)->limit(300)];
            }

            $json = json_decode($body, true);

            if (is_array($json)) {
                $code = strtolower((string) ($json['code'] ?? ''));

                if ($code === 'ok') {
                    return [true, 'ok'];
                }

                return [false, $this->describeArkeselError($json)];
            }

            // Arkesel can also answer with plain text ("OK:...").
            if (preg_match('/^OK/i', trim($body))) {
                return [true, 'ok'];
            }

            return [false, 'Unexpected gateway response: '.str($body)->limit(300)];
        } catch (\Throwable $exception) {
            return [false, 'Gateway exception: '.$exception->getMessage()];
        }
    }

    private function describeArkeselError(array $json): string
    {
        $code = (string) ($json['code'] ?? 'unknown');
        $message = (string) ($json['message'] ?? '');
        $description = self::ARKESEL_ERRORS[$code] ?? '';

        return 'Arkesel error '.$code
            .($description ? " — {$description}" : '')
            .($message ? " ({$message})" : '');
    }

    private function logFailure(string $phoneNumber, string $message, ?Customer $customer, ?int $announcementId, string $reason): bool
    {
        Log::error("SMS delivery failed for {$phoneNumber}: {$reason}");
        SmsLog::create([
            'customer_id' => $customer?->id,
            'announcement_id' => $announcementId,
            'phone_number' => $phoneNumber,
            'message' => $message,
            'status' => 'failed',
            'error_message' => $reason,
        ]);

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

    /**
     * Ordered list of formats to try for one number. Local (0XXXXXXXXX) is
     * preferred because it delivers faster and more reliably in Ghana; the
     * international form (233XXXXXXXXX) is always available as a fallback,
     * so a rejected local number is retried automatically in the other format.
     *
     * @return array<int, string>
     */
    protected function formatCandidates(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', trim($phone));

        if (!is_string($digits) || $digits === '') {
            return [preg_replace('/\s+/', '', $phone) ?? $phone];
        }

        $localFormat = (bool) config('services.arkesel.local_format', true);
        $local = null;
        $international = null;

        if (preg_match('/^233[0-9]{9}$/', $digits)) {
            $international = $digits;
            $local = '0'.substr($digits, 3);
        } elseif (preg_match('/^0[0-9]{9}$/', $digits)) {
            $local = $digits;
            $international = '233'.substr($digits, 1);
        }

        $candidates = [];

        if ($localFormat) {
            if ($local) {
                $candidates[] = $local;
            }
            if ($international) {
                $candidates[] = $international;
            }
        } else {
            if ($international) {
                $candidates[] = $international;
            }
            if ($local) {
                $candidates[] = $local;
            }
        }

        $candidates[] = $digits;

        return array_values(array_unique($candidates));
    }
}
