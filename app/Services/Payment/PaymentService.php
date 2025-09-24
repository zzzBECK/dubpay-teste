<?php

namespace App\Services\Payment;

use App\DTOs\PaymentRequest;
use App\DTOs\PaymentResponse;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private PaymentProviderRouter $router
    ) {}

    /**
     * Process a payment with retry logic and idempotency
     */
    public function processPayment(PaymentRequest $request, ?string $preferredProvider = null): PaymentResponse
    {
        // Check for existing payment with same idempotency key
        $existingPayment = Payment::where('idempotency_key', $request->idempotencyKey)->first();
        
        if ($existingPayment) {
            Log::info('Returning existing payment for idempotency key', [
                'idempotency_key' => $request->idempotencyKey,
                'payment_id' => $existingPayment->id
            ]);
            
            return new PaymentResponse(
                transactionId: $existingPayment->id,
                status: $existingPayment->status,
                providerTransactionId: $existingPayment->provider_transaction_id,
                providerName: $existingPayment->provider_name,
                message: 'Existing payment returned (idempotent)'
            );
        }

        return DB::transaction(function () use ($request, $preferredProvider) {
            // Create payment record
            $payment = Payment::create([
                'id' => Str::uuid(),
                'amount' => $request->amount,
                'currency' => $request->currency,
                'payment_method' => $request->paymentMethod,
                'customer_data' => $request->customerData,
                'idempotency_key' => $request->idempotencyKey,
                'description' => $request->description,
                'metadata' => $request->metadata,
                'status' => 'pending',
            ]);

            // Process payment with retry logic
            return $this->processPaymentWithRetry($payment, $request, $preferredProvider);
        });
    }

    /**
     * Process payment with retry logic
     */
    private function processPaymentWithRetry(Payment $payment, PaymentRequest $request, ?string $preferredProvider = null, int $attempt = 1): PaymentResponse
    {
        $maxAttempts = 3;
        
        try {
            // Select provider
            $provider = $this->router->routePayment($request->amount, $request->currency, $preferredProvider);
            
            Log::info('Processing payment attempt', [
                'payment_id' => $payment->id,
                'provider' => $provider->getName(),
                'attempt' => $attempt
            ]);

            // Create payment attempt record
            $paymentAttempt = PaymentAttempt::create([
                'id' => Str::uuid(),
                'payment_id' => $payment->id,
                'provider_name' => $provider->getName(),
                'attempt_number' => $attempt,
                'status' => 'processing',
                'request_data' => $request->toArray(),
            ]);

            // Process payment
            $response = $provider->processPayment($request);

            // Update payment attempt
            $paymentAttempt->update([
                'status' => $response->status,
                'provider_transaction_id' => $response->providerTransactionId,
                'response_data' => $response->rawResponse,
                'message' => $response->message,
            ]);

            // Update payment record
            $payment->update([
                'status' => $response->status,
                'provider_name' => $provider->getName(),
                'provider_transaction_id' => $response->providerTransactionId,
                'last_attempt_at' => now(),
            ]);

            // If payment failed and we can retry, try with different provider
            if ($response->isFailed() && $attempt < $maxAttempts) {
                Log::info('Payment failed, attempting retry', [
                    'payment_id' => $payment->id,
                    'attempt' => $attempt,
                    'next_attempt' => $attempt + 1
                ]);

                // Wait before retry (exponential backoff)
                usleep(pow(2, $attempt) * 100000); // 0.2s, 0.4s, 0.8s
                
                return $this->processPaymentWithRetry($payment, $request, null, $attempt + 1);
            }

            return new PaymentResponse(
                transactionId: $payment->id,
                status: $response->status,
                providerTransactionId: $response->providerTransactionId,
                providerName: $response->providerName,
                message: $response->message,
                rawResponse: $response->rawResponse
            );

        } catch (\Exception $e) {
            Log::error('Payment processing error', [
                'payment_id' => $payment->id,
                'attempt' => $attempt,
                'error' => $e->getMessage()
            ]);

            // Update payment attempt with error
            if (isset($paymentAttempt)) {
                $paymentAttempt->update([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]);
            }

            // If we can retry, try again
            if ($attempt < $maxAttempts) {
                usleep(pow(2, $attempt) * 100000);
                return $this->processPaymentWithRetry($payment, $request, null, $attempt + 1);
            }

            // Final failure
            $payment->update([
                'status' => 'failed',
                'last_attempt_at' => now(),
            ]);

            return new PaymentResponse(
                transactionId: $payment->id,
                status: 'failed',
                providerTransactionId: '',
                providerName: 'system',
                message: 'Payment processing failed after all retries: ' . $e->getMessage()
            );
        }
    }

    /**
     * Process webhook with idempotency
     */
    public function processWebhook(string $provider, array $payload, string $signature): array
    {
        $webhookId = $payload['id'] ?? hash('sha256', json_encode($payload));
        
        // Check if webhook was already processed
        $existingWebhook = DB::table('webhook_events')
            ->where('webhook_id', $webhookId)
            ->where('provider', $provider)
            ->first();

        if ($existingWebhook) {
            Log::info('Webhook already processed', [
                'webhook_id' => $webhookId,
                'provider' => $provider
            ]);
            
            return ['status' => 'already_processed'];
        }

        // Get provider and verify signature
        $providerInstance = $this->router->getProvider($provider);
        
        if (!$providerInstance) {
            throw new \InvalidArgumentException('Unknown payment provider: ' . $provider);
        }

        // In a real implementation, you'd verify the signature
        // For this mock, we'll skip signature verification
        
        // Record webhook event
        DB::table('webhook_events')->insert([
            'id' => Str::uuid(),
            'webhook_id' => $webhookId,
            'provider' => $provider,
            'payload' => json_encode($payload),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Process webhook
        $result = $providerInstance->processWebhook($payload);

        Log::info('Webhook processed successfully', [
            'webhook_id' => $webhookId,
            'provider' => $provider,
            'result' => $result
        ]);

        return $result;
    }
}