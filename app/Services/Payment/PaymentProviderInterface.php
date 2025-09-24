<?php

namespace App\Services\Payment;

use App\DTOs\PaymentRequest;
use App\DTOs\PaymentResponse;

interface PaymentProviderInterface
{
    /**
     * Process a payment
     */
    public function processPayment(PaymentRequest $request): PaymentResponse;

    /**
     * Get provider name
     */
    public function getName(): string;

    /**
     * Check if provider is available
     */
    public function isAvailable(): bool;

    /**
     * Get provider fee percentage
     */
    public function getFeePercentage(): float;

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool;

    /**
     * Process webhook event
     */
    public function processWebhook(array $payload): array;
}