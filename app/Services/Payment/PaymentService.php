<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Services\Payment\Providers\ProviderA;
use App\Services\Payment\Providers\ProviderB;
use Illuminate\Support\Str;

class PaymentService
{
    private array $providers;

    public function __construct()
    {
        $this->providers = [
            'provider_a' => new ProviderA(),
            'provider_b' => new ProviderB(),
        ];
    }

    public function createPayment(array $paymentData, ?string $preferredProvider = null): Payment
    {
        $provider = $this->selectProvider($preferredProvider);
        $externalId = $this->generateExternalId();

        
        $payment = Payment::create([
            'external_id' => $externalId,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'BRL',
            'provider' => $provider->getProviderName(),
            'status' => Payment::STATUS_PENDING,
        ]);

        try {
            
            $providerResponse = $provider->createPayment([
                'external_id' => $externalId,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'BRL',
                'description' => $paymentData['description'] ?? null,
            ]);

            
            $payment->update([
                'status' => $this->mapProviderStatus($providerResponse['status']),
                'provider_data' => $providerResponse,
            ]);

        } catch (\Exception $e) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'provider_data' => ['error' => $e->getMessage()],
            ]);
            throw $e;
        }

        return $payment;
    }

    public function processWebhook(string $providerName, array $webhookData): bool
    {
        $provider = $this->getProviderByName($providerName);
        if (!$provider) {
            return false;
        }

        try {
            $processedWebhook = $provider->processWebhook($webhookData);
            
            if (!$processedWebhook['success']) {
                return false;
            }

            $externalId = $processedWebhook['external_id'];
            $payment = Payment::where('external_id', $externalId)->first();

            if (!$payment) {
                return false;
            }

            
            $webhookId = $processedWebhook['provider_data']['webhook_id'] ?? null;
            if ($webhookId && $this->isWebhookAlreadyProcessed($payment, $webhookId)) {
                return true; 
            }

            
            $payment->update([
                'status' => $this->mapProviderStatus($processedWebhook['status']),
                'webhook_data' => array_merge(
                    $payment->webhook_data ?? [],
                    [$processedWebhook]
                ),
            ]);

            return true;

        } catch (\Exception $e) {
            \Log::error('Webhook processing failed', [
                'provider' => $providerName,
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData,
            ]);
            return false;
        }
    }

    public function getPayment(string $externalId): ?Payment
    {
        return Payment::where('external_id', $externalId)->first();
    }

    private function selectProvider(?string $preferredProvider = null): PaymentProviderInterface
    {
        if ($preferredProvider && isset($this->providers[$preferredProvider])) {
            return $this->providers[$preferredProvider];
        }

        
        $providerKeys = array_keys($this->providers);
        $selectedKey = $providerKeys[time() % count($providerKeys)];
        
        return $this->providers[$selectedKey];
    }

    private function getProviderByName(string $providerName): ?PaymentProviderInterface
    {
        foreach ($this->providers as $provider) {
            if (strtolower($provider->getProviderName()) === strtolower($providerName)) {
                return $provider;
            }
        }
        return null;
    }

    private function generateExternalId(): string
    {
        return 'PAY_' . Str::random(16);
    }

    private function mapProviderStatus(string $providerStatus): string
    {
        return match(strtolower($providerStatus)) {
            'pending', 'pending_authorization' => Payment::STATUS_PENDING,
            'processing' => Payment::STATUS_PROCESSING,
            'completed' => Payment::STATUS_COMPLETED,
            'failed' => Payment::STATUS_FAILED,
            'cancelled' => Payment::STATUS_CANCELLED,
            default => Payment::STATUS_PENDING,
        };
    }

    private function isWebhookAlreadyProcessed(Payment $payment, string $webhookId): bool
    {
        if (!$payment->webhook_data) {
            return false;
        }

        foreach ($payment->webhook_data as $webhook) {
            if (isset($webhook['provider_data']['webhook_id']) && 
                $webhook['provider_data']['webhook_id'] === $webhookId) {
                return true;
            }
        }

        return false;
    }
}