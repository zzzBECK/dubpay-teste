<?php

namespace App\DTOs;

class PaymentRequest
{
    public function __construct(
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $paymentMethod,
        public readonly array $customerData,
        public readonly string $idempotencyKey,
        public readonly ?string $description = null,
        public readonly array $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->paymentMethod,
            'customer_data' => $this->customerData,
            'idempotency_key' => $this->idempotencyKey,
            'description' => $this->description,
            'metadata' => $this->metadata,
        ];
    }
}