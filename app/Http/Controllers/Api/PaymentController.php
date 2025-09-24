<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function createPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|size:3',
            'description' => 'sometimes|string|max:255',
            'provider' => 'sometimes|string|in:provider_a,provider_b',
        ]);

        try {
            $payment = $this->paymentService->createPayment(
                $request->only(['amount', 'currency', 'description']),
                $request->input('provider')
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully',
                'data' => [
                    'payment' => $payment
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Payment creation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment creation failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getPayment(Request $request, string $externalId)
    {
        $payment = $this->paymentService->getPayment($externalId);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payment' => $payment
            ]
        ]);
    }

    public function processWebhook(Request $request, string $provider)
    {
        
        if (!in_array(strtolower($provider), ['providera', 'providerb'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid provider'
            ], 400);
        }

        try {
            $webhookData = $request->all();
            
            
            Log::info('Webhook received', [
                'provider' => $provider,
                'data' => $webhookData
            ]);

            $processed = $this->paymentService->processWebhook($provider, $webhookData);

            if ($processed) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook processed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook processing failed'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'webhook_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listPayments(Request $request)
    {
        $payments = \App\Models\Payment::orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }
}
