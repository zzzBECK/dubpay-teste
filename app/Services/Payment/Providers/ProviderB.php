<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\PaymentProviderInterface;
use Illuminate\Support\Str;

class ProviderB implements PaymentProviderInterface
{
    public function createPayment(array $paymentData): array
    {
        
        $externalId = 'PB_' . Str::random(12);
        
        return [
            'success' => true,
            'external_id' => $externalId,
            'status' => 'pending',
            'message' => 'Payment created successfully with Provider B',
            'provider_data' => [
                'provider_payment_id' => $externalId,
                'provider_status' => 'pending_authorization',
                'provider_fee' => $paymentData['amount'] * 0.019, 
                'estimated_completion' => now()->addMinutes(15)->toISOString(),
                'requires_3ds' => true,
            ]
        ];
    }

    public function getPaymentStatus(string $externalId): array
    {
        
        $rand = rand(1, 100);
        
        if ($rand <= 70) {
            $status = 'completed';
        } elseif ($rand <= 85) {
            $status = 'processing';
        } elseif ($rand <= 95) {
            $status = 'pending';
        } else {
            $status = 'failed';
        }
        
        return [
            'success' => true,
            'external_id' => $externalId,
            'status' => $status,
            'provider_data' => [
                'provider_payment_id' => $externalId,
                'provider_status' => $status,
                'authorization_code' => $status === 'completed' ? Str::random(8) : null,
                'updated_at' => now()->toISOString(),
            ]
        ];
    }

    public function processWebhook(array $webhookData): array
    {
        
        return [
            'success' => true,
            'external_id' => $webhookData['transaction_id'] ?? null,
            'status' => $this->mapProviderBStatus($webhookData['event_type'] ?? 'completed'),
            'webhook_processed' => true,
            'provider_data' => [
                'webhook_id' => $webhookData['webhook_id'] ?? Str::random(15),
                'event_type' => $webhookData['event_type'] ?? 'transaction.completed',
                'authorization_code' => $webhookData['auth_code'] ?? null,
                'processed_at' => now()->toISOString(),
            ]
        ];
    }

    private function mapProviderBStatus(string $eventType): string
    {
        return match($eventType) {
            'transaction.completed' => 'completed',
            'transaction.failed' => 'failed',
            'transaction.cancelled' => 'cancelled',
            default => 'processing'
        };
    }

    public function getProviderName(): string
    {
        return 'ProviderB';
    }
}