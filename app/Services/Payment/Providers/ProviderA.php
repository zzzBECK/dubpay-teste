<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\PaymentProviderInterface;
use Illuminate\Support\Str;

class ProviderA implements PaymentProviderInterface
{
    public function createPayment(array $paymentData): array
    {
        
        $externalId = 'PA_' . Str::random(10);
        
        return [
            'success' => true,
            'external_id' => $externalId,
            'status' => 'processing',
            'message' => 'Payment created successfully with Provider A',
            'provider_data' => [
                'provider_payment_id' => $externalId,
                'provider_status' => 'processing',
                'provider_fee' => $paymentData['amount'] * 0.029, 
                'estimated_completion' => now()->addMinutes(5)->toISOString(),
            ]
        ];
    }

    public function getPaymentStatus(string $externalId): array
    {
        
        $statuses = ['processing', 'completed', 'failed'];
        $randomStatus = $statuses[array_rand($statuses)];
        
        return [
            'success' => true,
            'external_id' => $externalId,
            'status' => $randomStatus,
            'provider_data' => [
                'provider_payment_id' => $externalId,
                'provider_status' => $randomStatus,
                'updated_at' => now()->toISOString(),
            ]
        ];
    }

    public function processWebhook(array $webhookData): array
    {
        
        return [
            'success' => true,
            'external_id' => $webhookData['payment_id'] ?? null,
            'status' => $webhookData['status'] ?? 'completed',
            'webhook_processed' => true,
            'provider_data' => [
                'webhook_id' => $webhookData['id'] ?? Str::random(10),
                'webhook_type' => $webhookData['type'] ?? 'payment.completed',
                'processed_at' => now()->toISOString(),
            ]
        ];
    }

    public function getProviderName(): string
    {
        return 'ProviderA';
    }
}