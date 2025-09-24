<?php

namespace App\DTOs;

class PaymentResponse
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $status,
        public readonly string $providerTransactionId,
        public readonly string $providerName,
        public readonly ?string $message = null,
        public readonly array $rawResponse = []
    ) {}

    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'status' => $this->status,
            'provider_transaction_id' => $this->providerTransactionId,
            'provider_name' => $this->providerName,
            'message' => $this->message,
            'raw_response' => $this->rawResponse,
        ];
    }

    public function isSuccess(): bool
    {
        return in_array($this->status, ['success', 'completed', 'approved']);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'declined', 'error']);
    }
}