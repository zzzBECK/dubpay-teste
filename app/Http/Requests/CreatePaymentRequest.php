<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'currency' => 'sometimes|string|size:3|in:BRL,USD,EUR',
            'description' => 'sometimes|string|max:255',
            'provider' => 'sometimes|string|in:provider_a,provider_b',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'The amount field is required.',
            'amount.numeric' => 'The amount must be a number.',
            'amount.min' => 'The amount must be at least 0.01.',
            'amount.max' => 'The amount must not exceed 999,999.99.',
            'currency.size' => 'The currency must be exactly 3 characters.',
            'currency.in' => 'The currency must be one of: BRL, USD, EUR.',
            'provider.in' => 'The provider must be either provider_a or provider_b.',
        ];
    }
}
