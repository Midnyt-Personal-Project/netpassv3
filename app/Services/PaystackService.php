<?php

namespace App\Services;

use Illuminate\Support\Facades\{Http, Log};

use App\Models\Payment;

class PaystackService
{
    protected $secretKey;
    protected $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
    }

    /**
     * Create a Paystack Subaccount for a Location.
     * This routes payments directly to the Location owner while taking platform commissions.
     */
    public function createSubaccount(string $businessName, string $bankCode, string $accountNumber, float $percentageCharge): ?array
    {
        if (blank($this->secretKey)) {
            Log::error('Paystack is not configured: PAYSTACK_SECRET_KEY is missing.');
            return null;
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->post("{$this->baseUrl}/subaccount", [
                    'business_name' => $businessName,
                    'settlement_bank' => $bankCode,
                    'account_number' => $accountNumber,
                    'percentage_charge' => $percentageCharge,
                ]);

            $data = $response->json('data');
            if ($response->successful() && is_array($data)) {
                return $data;
            }

            Log::error('Paystack Subaccount Creation Failed', [
                'response' => $response->body()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Paystack Subaccount Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Initialize a Paystack transaction routing split payments to location's subaccount.
     */
    public function initializeTransaction(
        string $email,
        float $amount,
        string $reference,
        string $callbackUrl,
        string $subaccountCode,
        array $metadata = [],
    ): ?array
    {
        if (blank($this->secretKey)) {
            Log::error('Paystack is not configured: PAYSTACK_SECRET_KEY is missing.');
            return null;
        }

        // Paystack expects the currency's lowest unit (GHS 10.00 = 1000 pesewas).
        $amountInLowestUnit = (int) round($amount * 100);

        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->post("{$this->baseUrl}/transaction/initialize", [
                    'email' => $email,
                    'amount' => $amountInLowestUnit,
                    'currency' => 'GHS',
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'subaccount' => $subaccountCode,
                    'metadata' => $metadata,
                ]);

            $data = $response->json('data');
            if ($response->successful() && is_array($data) && isset($data['authorization_url'])) {
                return $data;
            }

            Log::error('Paystack Initialize Transaction Failed', [
                'response' => $response->body()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Paystack Initialize Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function transactionMatchesPayment(array $transaction, Payment $payment): bool
    {
        return ($transaction['status'] ?? null) === 'success'
            && hash_equals((string) $payment->paystack_reference, (string) ($transaction['reference'] ?? ''))
            && strtoupper((string) ($transaction['currency'] ?? '')) === strtoupper($payment->currency)
            && (int) ($transaction['amount'] ?? -1) === (int) round((float) $payment->amount * 100);
    }

    public function hasValidWebhookSignature(string $payload, ?string $signature): bool
    {
        if (blank($this->secretKey) || blank($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $this->secretKey), $signature);
    }

    /**
     * Verify a transaction on Paystack.
     */
    public function verifyTransaction(string $reference): ?array
    {
        if (blank($this->secretKey)) {
            Log::error('Paystack is not configured: PAYSTACK_SECRET_KEY is missing.');
            return null;
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->get("{$this->baseUrl}/transaction/verify/".urlencode($reference));

            $data = $response->json('data');
            if ($response->successful() && is_array($data)) {
                return $data;
            }

            Log::error('Paystack Verify Transaction Failed', [
                'reference' => $reference,
                'response' => $response->body()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Paystack Verify Exception: ' . $e->getMessage());
            return null;
        }
    }
}
