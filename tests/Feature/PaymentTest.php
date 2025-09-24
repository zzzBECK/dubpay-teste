<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_can_process_payment_with_stripe(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', [
            'amount' => '25.00',
            'currency' => 'USD',
            'payment_method' => 'card',
            'customer_data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ],
            'description' => 'Test payment',
            'provider' => 'stripe'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'payment' => [
                        'transaction_id',
                        'status',
                        'provider_transaction_id',
                        'provider_name'
                    ]
                ]);

        $data = $response->json();
        $this->assertEquals('stripe', $data['payment']['provider_name']);
        $this->assertEquals('success', $data['payment']['status']);
    }

    public function test_can_process_payment_with_paypal(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', [
            'amount' => '30.00',
            'currency' => 'USD',
            'payment_method' => 'paypal',
            'customer_data' => [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com'
            ],
            'description' => 'Test PayPal payment',
            'provider' => 'paypal'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'payment' => [
                        'transaction_id',
                        'status',
                        'provider_transaction_id',
                        'provider_name'
                    ]
                ]);

        $data = $response->json();
        $this->assertEquals('paypal', $data['payment']['provider_name']);
        $this->assertEquals('success', $data['payment']['status']);
    }

    public function test_payment_fails_with_large_amount(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', [
            'amount' => '15000.00', // Large amount to trigger failure
            'currency' => 'USD',
            'payment_method' => 'card',
            'customer_data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ],
            'description' => 'Large payment test',
            'provider' => 'stripe'
        ]);

        $response->assertStatus(202); // Accepted but may fail
        
        $data = $response->json();
        $this->assertContains($data['payment']['status'], ['failed', 'pending']);
    }

    public function test_idempotency_prevents_duplicate_processing(): void
    {
        $idempotencyKey = 'unique-test-key-123';
        
        $paymentData = [
            'amount' => '50.00',
            'currency' => 'USD',
            'payment_method' => 'card',
            'customer_data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ],
            'description' => 'Idempotency test',
            'idempotency_key' => $idempotencyKey
        ];

        // First request
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        // Second request with same idempotency key
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments', $paymentData);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $data1 = $response1->json();
        $data2 = $response2->json();

        // Should return same transaction ID
        $this->assertEquals($data1['payment']['transaction_id'], $data2['payment']['transaction_id']);
    }

    public function test_can_process_webhook(): void
    {
        $webhookPayload = [
            'id' => 'evt_test_webhook',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'status' => 'succeeded'
                ]
            ]
        ];

        $response = $this->postJson('/api/webhooks/stripe', $webhookPayload, [
            'X-Signature' => 'test_signature'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'result'
                ]);
    }

    public function test_webhook_idempotency(): void
    {
        $webhookPayload = [
            'id' => 'evt_duplicate_test',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_456',
                    'status' => 'succeeded'
                ]
            ]
        ];

        // Process webhook first time
        $response1 = $this->postJson('/api/webhooks/stripe', $webhookPayload, [
            'X-Signature' => 'test_signature'
        ]);

        // Process same webhook again
        $response2 = $this->postJson('/api/webhooks/stripe', $webhookPayload, [
            'X-Signature' => 'test_signature'
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $data2 = $response2->json();
        $this->assertEquals('already_processed', $data2['result']['status']);
    }
}