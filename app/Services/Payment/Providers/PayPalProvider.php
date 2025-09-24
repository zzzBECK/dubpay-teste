<?php

namespace App\Services\Payment\Providers;

use App\DTOs\PaymentRequest;
use App\DTOs\PaymentResponse;
use App\Services\Payment\PaymentProviderInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PayPalProvider implements PaymentProviderInterface
{
    private bool $isAvailable = true;
    private float $feePercentage = 3.4;

    public function processPayment(PaymentRequest $request): PaymentResponse
    {
        Log::info('Processing PayPal payment', [
            'amount' => $request->amount,
            'currency' => $request->currency,
            'idempotency_key' => $request->idempotencyKey
        ]);

        // Simulate API call delay (PayPal is typically slower)
        usleep(800000); // 0.8 seconds

        // Mock different scenarios based on currency and amount
        $amount = (float) $request->amount;
        
        if (!in_array($request->currency, ['USD', 'EUR', 'BRL'])) {
            // Simulate currency not supported
            return new PaymentResponse(
                transactionId: Str::uuid()->toString(),
                status: 'failed',
                providerTransactionId: 'PAYID-' . strtoupper(Str::random(12)),
                providerName: 'paypal',
                message: 'Currency not supported',
                rawResponse: [
                    'error' => [
                        'name' => 'CURRENCY_NOT_SUPPORTED',
                        'message' => 'Currency is not supported'
                    ]
                ]
            );
        }

        if ($amount < 1) {
            // Simulate minimum amount error
            return new PaymentResponse(
                transactionId: Str::uuid()->toString(),
                status: 'failed',
                providerTransactionId: 'PAYID-' . strtoupper(Str::random(12)),
                providerName: 'paypal',
                message: 'Amount too small',
                rawResponse: [
                    'error' => [
                        'name' => 'AMOUNT_TOO_SMALL',
                        'message' => 'Minimum amount is 1.00'
                    ]
                ]
            );
        }

        if ($amount > 8000) {
            // Simulate pending for large amounts (needs review)
            return new PaymentResponse(
                transactionId: Str::uuid()->toString(),
                status: 'pending',
                providerTransactionId: 'PAYID-' . strtoupper(Str::random(12)),
                providerName: 'paypal',
                message: 'Payment under review',
                rawResponse: [
                    'status' => 'PENDING',
                    'status_details' => [
                        'reason' => 'PENDING_REVIEW'
                    ]
                ]
            );
        }

        // Success for normal payments
        return new PaymentResponse(
            transactionId: Str::uuid()->toString(),
            status: 'success',
            providerTransactionId: 'PAYID-' . strtoupper(Str::random(12)),
            providerName: 'paypal',
            message: 'Payment completed successfully',
            rawResponse: [
                'status' => 'COMPLETED',
                'amount' => [
                    'currency_code' => $request->currency,
                    'value' => number_format($amount, 2, '.', '')
                ],
                'create_time' => now()->toISOString(),
                'update_time' => now()->toISOString()
            ]
        );
    }

    public function getName(): string
    {
        return 'paypal';
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
        // Mock PayPal webhook verification
        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return hash_equals($expectedSignature, $signature);
    }

    public function processWebhook(array $payload): array
    {
        Log::info('Processing PayPal webhook', $payload);

        return [
            'status' => 'processed',
            'event_type' => $payload['event_type'] ?? 'unknown',
            'transaction_id' => $payload['resource']['id'] ?? null,
        ];
    }
}