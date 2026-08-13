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
     *
     * Every attempt is written to the application log with the [SMS] tag and
     * to the sms_logs table (visible in Admin > Logs) with the exact gateway
     * reply, so failures are always diagnosable.
     */
    public function sendSms(string $phoneNumber, string $message, ?Customer $customer = null, ?int $announcementId = null, string $type = SmsLog::TYPE_OTHER): bool
    {
        $startedAt = microtime(true);
        $context = [
            'type' => $type,
            'phone' => $phoneNumber,
            'customer' => $customer ? ($customer->voucher_code ?: $customer->username) : null,
            'announcement_id' => $announcementId,
            'sender_id' => $this->senderId,
        ];

        if (blank($this->apiKey)) {
            Log::error('[SMS] Not sent — no API key configured.', $context);

            return $this->logFailure($phoneNumber, $message, $customer, $announcementId, $type, 'ARKESEL_SMS_API_KEY is not configured in .env', null, 0);
        }

        $attempts = $this->formatCandidates($phoneNumber);
        $reasons = [];
        $lastRawResponse = null;

        foreach ($attempts as $index => $formatted) {
            [$ok, $reason, $rawResponse] = $this->requestGateway($formatted, $message);
            $lastRawResponse = $rawResponse;
            $reasons[] = $formatted.': '.$reason;

            if ($ok) {
                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

                SmsLog::create([
                    'customer_id' => $customer?->id,
                    'announcement_id' => $announcementId,
                    'phone_number' => $formatted,
                    'message' => $message,
                    'status' => 'sent',
                    'type' => $type,
                    'attempts' => $index + 1,
                    'gateway_response' => $this->summarizeGatewayResponse($rawResponse),
                ]);

                Log::info('[SMS] Sent successfully.', $context + [
                    'to' => $formatted,
                    'attempt' => $index + 1,
                    'duration_ms' => $durationMs,
                    'gateway' => $rawResponse,
                ]);

                return true;
            }

            Log::warning('[SMS] Attempt rejected by gateway.', $context + [
                'to' => $formatted,
                'attempt' => $index + 1,
                'reason' => $reason,
                'gateway' => $rawResponse,
            ]);
        }

        $reason = implode(' | ', $reasons);

        return $this->logFailure($attempts[0] ?? $phoneNumber, $message, $customer, $announcementId, $type, $reason, $lastRawResponse, count($attempts));
    }

    /**
     * Low-level gateway call. Returns [bool $ok, string $reason, ?string $rawBody].
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
                return [false, 'Gateway HTTP '.$response->status().': '.str($body)->limit(300), $body];
            }

            $json = json_decode($body, true);

            if (is_array($json)) {
                $code = strtolower((string) ($json['code'] ?? ''));

                if ($code === 'ok') {
                    return [true, 'ok', $body];
                }

                return [false, $this->describeArkeselError($json), $body];
            }

            // Arkesel can also answer with plain text ("OK:...").
            if (preg_match('/^OK/i', trim($body))) {
                return [true, 'ok', $body];
            }

            return [false, 'Unexpected gateway response: '.str($body)->limit(300), $body];
        } catch (\Throwable $exception) {
            return [false, 'Gateway exception: '.$exception->getMessage(), null];
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

    /** Keep the stored gateway reply readable: decode JSON or trim long text. */
    private function summarizeGatewayResponse(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $json = json_decode($raw, true);

        if (is_array($json)) {
            return (string) json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return str($raw)->limit(2000)->toString();
    }

    private function logFailure(string $phoneNumber, string $message, ?Customer $customer, ?int $announcementId, string $type, string $reason, ?string $rawResponse, int $attempts): bool
    {
        Log::error('[SMS] Delivery failed.', [
            'type' => $type,
            'phone' => $phoneNumber,
            'customer' => $customer ? ($customer->voucher_code ?: $customer->username) : null,
            'announcement_id' => $announcementId,
            'attempts' => $attempts,
            'reason' => $reason,
            'gateway' => $rawResponse,
        ]);

        SmsLog::create([
            'customer_id' => $customer?->id,
            'announcement_id' => $announcementId,
            'phone_number' => $phoneNumber,
            'message' => $message,
            'status' => 'failed',
            'type' => $type,
            'attempts' => max(1, $attempts),
            'error_message' => $reason,
            'gateway_response' => $this->summarizeGatewayResponse($rawResponse),
        ]);

        return false;
    }

    public function sendCredentials(Customer $customer, string $packageName): bool
    {
        $expiryDate = $customer->expires_at ? $customer->expires_at->format('d M Y H:i') : 'Unlimited';
        $voucher = $customer->voucher_code ?: $customer->username;
        $message = "Welcome to Oyalo WiFi!\n\nYour WiFi voucher: {$voucher}\nUse this same voucher in both MikroTik username and password fields.\nPackage: {$packageName}\nExpires: {$expiryDate}\n\nEnjoy fast internet!";

        return $this->sendSms($customer->phone_number, $message, $customer, null, SmsLog::TYPE_VOUCHER);
    }

    public function sendExpiryNotification(Customer $customer): bool
    {
        return $this->sendSms($customer->phone_number, 'Hello, your Oyalo WiFi package has expired. Open your browser and connect to renew your internet access.', $customer, null, SmsLog::TYPE_EXPIRY);
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
