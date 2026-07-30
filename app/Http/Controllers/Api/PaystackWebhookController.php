<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\{PaymentFulfillmentService, PaystackService};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private readonly PaystackService $paystack,
        private readonly PaymentFulfillmentService $paymentFulfillment,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        if (!$this->paystack->hasValidWebhookSignature(
            $rawPayload,
            $request->header('x-paystack-signature'),
        )) {
            return response()->json(['error' => 'Invalid webhook signature'], 401);
        }

        $event = $request->json()->all();
        if (($event['event'] ?? null) !== 'charge.success') {
            return response()->json(['status' => 'ignored']);
        }

        $transaction = $event['data'] ?? null;
        $reference = is_array($transaction) ? ($transaction['reference'] ?? null) : null;
        if (!is_string($reference) || $reference === '') {
            return response()->json(['error' => 'Missing transaction reference'], 422);
        }

        $payment = Payment::where('paystack_reference', $reference)->first();
        if (!$payment) {
            Log::warning('Paystack webhook referenced an unknown payment.', ['reference' => $reference]);

            return response()->json(['error' => 'Payment not found'], 404);
        }

        if (!$this->paystack->transactionMatchesPayment($transaction, $payment)) {
            Log::warning('Paystack webhook did not match the stored payment.', [
                'payment_id' => $payment->id,
                'reference' => $reference,
            ]);

            return response()->json(['error' => 'Transaction does not match payment'], 422);
        }

        try {
            $result = $this->paymentFulfillment->fulfill($payment, null, $request->ip());
        } catch (Throwable $exception) {
            Log::error('Paystack webhook fulfillment failed.', [
                'payment_id' => $payment->id,
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            // A non-2xx response asks Paystack to retry the webhook.
            return response()->json(['error' => 'Fulfillment failed'], 500);
        }

        return response()->json([
            'status' => 'success',
            'newly_processed' => $result['newly_processed'],
        ]);
    }
}
