<?php

namespace App\Http\Controllers;

use App\DTOs\PaymentRequest;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Process a payment
     */
    public function processPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:USD,EUR,BRL,GBP',
            'payment_method' => 'required|string',
            'customer_data' => 'required|array',
            'customer_data.name' => 'required|string',
            'customer_data.email' => 'required|email',
            'description' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'provider' => 'nullable|string|in:stripe,paypal',
            'idempotency_key' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $paymentRequest = new PaymentRequest(
                amount: $request->input('amount'),
                currency: $request->input('currency'),
                paymentMethod: $request->input('payment_method'),
                customerData: $request->input('customer_data'),
                idempotencyKey: $request->input('idempotency_key', Str::uuid()->toString()),
                description: $request->input('description'),
                metadata: $request->input('metadata', [])
            );

            $response = $this->paymentService->processPayment(
                $paymentRequest, 
                $request->input('provider')
            );

            return response()->json([
                'message' => 'Payment processed',
                'payment' => $response->toArray()
            ], $response->isSuccess() ? 201 : 202);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle webhook from payment providers
     */
    public function handleWebhook(Request $request, string $provider): JsonResponse
    {
        try {
            $payload = $request->all();
            $signature = $request->header('X-Signature', '');

            $result = $this->paymentService->processWebhook($provider, $payload, $signature);

            return response()->json([
                'message' => 'Webhook processed successfully',
                'result' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(Request $request, string $paymentId): JsonResponse
    {
        try {
            $payment = \App\Models\Payment::with('attempts')->findOrFail($paymentId);

            return response()->json([
                'payment' => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'provider_name' => $payment->provider_name,
                    'provider_transaction_id' => $payment->provider_transaction_id,
                    'created_at' => $payment->created_at,
                    'last_attempt_at' => $payment->last_attempt_at,
                    'attempts' => $payment->attempts->map(function ($attempt) {
                        return [
                            'id' => $attempt->id,
                            'provider_name' => $attempt->provider_name,
                            'attempt_number' => $attempt->attempt_number,
                            'status' => $attempt->status,
                            'message' => $attempt->message,
                            'created_at' => $attempt->created_at,
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Payment not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * List user payments
     */
    public function listPayments(Request $request): JsonResponse
    {
        try {
            $payments = $request->user()->payments()
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'payments' => $payments->items(),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'last_page' => $payments->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to list payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}