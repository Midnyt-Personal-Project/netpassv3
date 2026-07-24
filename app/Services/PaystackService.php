<?php

namespace App\Services;

use Illuminate\Support\Facades\{Http, Log};

use App\Models\Location;

class PaystackService
{
    protected $secretKey;
    protected $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = env('PAYSTACK_SECRET_KEY', null);
    }

    /**
     * Create a Paystack Subaccount for a Location.
     * This routes payments directly to the Location owner while taking platform commissions.
     */
    public function createSubaccount(string $businessName, string $bankCode, string $accountNumber, float $percentageCharge)
    {
        if (blank($this->secretKey)) {
            Log::error('Paystack is not configured: PAYSTACK_SECRET_KEY is missing.');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/subaccount", [
                'business_name' => $businessName,
                'settlement_bank' => $bankCode,
                'account_number' => $accountNumber,
                'percentage_charge' => $percentageCharge, // Oyalo platform commission
            ]);

            if ($response->successful()) {
                return $response->json()['data']; // Returns subaccount code (e.g. ACCT_xxxxxxxx)
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
    public function initializeTransaction(string $email, float $amount, string $reference, string $callbackUrl, string $subaccountCode)
    {
        if (blank($this->secretKey)) {
            Log::error('Paystack is not configured: PAYSTACK_SECRET_KEY is missing.');
            return null;
        }

        // Paystack amounts are in kobo (GHS 10.00 = 1000)
        $amountInKobo = round($amount * 100);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/transaction/initialize", [
                'email' => $email,
                'amount' => $amountInKobo,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'subaccount' => $subaccountCode,
            ]);

            if ($response->successful()) {
                return $response->json()['data']; // Returns authorization_url & access_code
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

    /**
     * Verify a transaction on Paystack.
     */
    public function verifyTransaction(string $reference)
    {
        if (blank($this->secretKey)) {
            Log::error('Paystack is not configured: PAYSTACK_SECRET_KEY is missing.');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
            ])->get("{$this->baseUrl}/transaction/verify/" . urlencode($reference));

            if ($response->successful()) {
                return $response->json()['data'];
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
