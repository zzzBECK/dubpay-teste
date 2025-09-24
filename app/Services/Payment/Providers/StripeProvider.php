<?php

namespace App\Services\Payment\Providers;

use App\DTOs\PaymentRequest;
use App\DTOs\PaymentResponse;
use App\Services\Payment\PaymentProviderInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class StripeProvider implements PaymentProviderInterface
{
    private bool $isAvailable = true;
    private float $feePercentage = 2.9;

    public function processPayment(PaymentRequest $request): PaymentResponse
    {
        Log::info('Processing Stripe payment', [
            'amount' => $request->amount,
            'currency' => $request->currency,
            'idempotency_key' => $request->idempotencyKey
        ]);

        // Simulate API call delay
        usleep(500000); // 0.5 seconds

        // Mock different scenarios based on amount
        $amount = (float) $request->amount;
        
        if ($amount > 10000) {
            // Simulate failure for large amounts
            return new PaymentResponse(
                transactionId: Str::uuid()->toString(),
                status: 'failed',
                providerTransactionId: 'pi_' . Str::random(24),
                providerName: 'stripe',
                message: 'Payment declined by issuer',
                rawResponse: [
                    'error' => [
                        'type' => 'card_error',
                        'code' => 'card_declined',
                        'decline_code' => 'generic_decline'
                    ]
                ]
            );
        }

        if ($amount > 5000) {
            // Simulate pending status for medium amounts
            return new PaymentResponse(
                transactionId: Str::uuid()->toString(),
                status: 'pending',
                providerTransactionId: 'pi_' . Str::random(24),
                providerName: 'stripe',
                message: 'Payment is being processed',
                rawResponse: [
                    'status' => 'processing',
                    'next_action' => [
                        'type' => '3d_secure_redirect'
                    ]
                ]
            );
        }

        // Success for normal amounts
        return new PaymentResponse(
            transactionId: Str::uuid()->toString(),
            status: 'success',
            providerTransactionId: 'pi_' . Str::random(24),
            providerName: 'stripe',
            message: 'Payment processed successfully',
            rawResponse: [
                'status' => 'succeeded',
                'amount_received' => $amount * 100, // Stripe uses cents
                'currency' => $request->currency,
                'created' => time()
            ]
        );
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function getFeePercentage(): float
    {
        return $this->feePercentage;
    }

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        // Mock signature verification
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    public function processWebhook(array $payload): array
    {
        Log::info('Processing Stripe webhook', $payload);

        return [
            'status' => 'processed',
            'event_type' => $payload['type'] ?? 'unknown',
            'transaction_id' => $payload['data']['object']['id'] ?? null,
        ];
    }
}