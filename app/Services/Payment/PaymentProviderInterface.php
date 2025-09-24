<?php

namespace App\Services\Payment;

interface PaymentProviderInterface
{
    public function createPayment(array $paymentData): array;

    public function getPaymentStatus(string $externalId): array;

    public function processWebhook(array $webhookData): array;

    public function getProviderName(): string;
}