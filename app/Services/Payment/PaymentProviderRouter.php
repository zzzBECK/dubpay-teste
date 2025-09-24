<?php

namespace App\Services\Payment;

use App\Services\Payment\Providers\StripeProvider;
use App\Services\Payment\Providers\PayPalProvider;

class PaymentProviderRouter
{
    private array $providers;

    public function __construct()
    {
        $this->providers = [
            'stripe' => new StripeProvider(),
            'paypal' => new PayPalProvider(),
        ];
    }

    /**
     * Route payment to the best available provider based on strategy
     */
    public function routePayment(string $amount, string $currency, ?string $preferredProvider = null): PaymentProviderInterface
    {
        // If a specific provider is requested and available, use it
        if ($preferredProvider && isset($this->providers[$preferredProvider])) {
            $provider = $this->providers[$preferredProvider];
            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        // Apply routing strategy based on amount, currency, and provider status
        return $this->selectOptimalProvider($amount, $currency);
    }

    /**
     * Select optimal provider based on multiple factors
     */
    private function selectOptimalProvider(string $amount, string $currency): PaymentProviderInterface
    {
        $availableProviders = array_filter($this->providers, fn($provider) => $provider->isAvailable());
        
        if (empty($availableProviders)) {
            throw new \RuntimeException('No payment providers available');
        }

        $amountFloat = (float) $amount;

        // Strategy 1: Currency-based routing
        if ($currency === 'BRL') {
            // For Brazilian Real, prefer local providers (but we only have mocks here)
            // In real implementation, would prefer PagSeguro/MercadoPago
            return $this->getProviderWithLowestFee($availableProviders);
        }

        // Strategy 2: Amount-based routing
        if ($amountFloat > 10000) {
            // For large amounts, prefer more reliable providers
            if (isset($availableProviders['stripe'])) {
                return $availableProviders['stripe'];
            }
        }

        if ($amountFloat < 10) {
            // For small amounts, prefer lower-fee providers
            return $this->getProviderWithLowestFee($availableProviders);
        }

        // Strategy 3: Load balancing - alternate between providers
        $providerNames = array_keys($availableProviders);
        $selectedProvider = $providerNames[time() % count($providerNames)];
        
        return $availableProviders[$selectedProvider];
    }

    /**
     * Get provider with lowest fee
     */
    private function getProviderWithLowestFee(array $providers): PaymentProviderInterface
    {
        return array_reduce($providers, function ($carry, $provider) {
            return $carry === null || $provider->getFeePercentage() < $carry->getFeePercentage() 
                ? $provider 
                : $carry;
        });
    }

    /**
     * Get all available providers
     */
    public function getAvailableProviders(): array
    {
        return array_filter($this->providers, fn($provider) => $provider->isAvailable());
    }

    /**
     * Get provider by name
     */
    public function getProvider(string $name): ?PaymentProviderInterface
    {
        return $this->providers[$name] ?? null;
    }
}