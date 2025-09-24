<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private function getAuthenticatedUser()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        return [$user, $token];
    }

    public function test_authenticated_user_can_create_payment(): void
    {
        [$user, $token] = $this->getAuthenticatedUser();

        $paymentData = [
            'amount' => 100.50,
            'currency' => 'BRL',
            'description' => 'Test payment',
            'provider' => 'provider_a'
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payments', $paymentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'payment' => [
                        'id', 'external_id', 'amount', 'currency', 
                        'provider', 'status', 'created_at'
                    ]
                ]
            ]);

        $this->assertDatabaseHas('payments', [
            'amount' => 100.50,
            'currency' => 'BRL',
            'provider' => 'ProviderA'
        ]);
    }

    public function test_user_cannot_create_payment_without_authentication(): void
    {
        $paymentData = [
            'amount' => 100.50,
            'currency' => 'BRL'
        ];

        $response = $this->postJson('/api/payments', $paymentData);

        $response->assertStatus(401);
    }

    public function test_payment_creation_requires_valid_amount(): void
    {
        [$user, $token] = $this->getAuthenticatedUser();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payments', [
                'amount' => -10,
                'currency' => 'BRL'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_user_can_retrieve_payment(): void
    {
        [$user, $token] = $this->getAuthenticatedUser();

        
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payments', [
                'amount' => 100.00,
                'currency' => 'BRL'
            ]);

        $externalId = $createResponse->json('data.payment.external_id');

        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/payments/{$externalId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payment' => [
                        'id', 'external_id', 'amount', 'currency', 
                        'provider', 'status'
                    ]
                ]
            ]);
    }

    public function test_webhook_can_be_processed(): void
    {
        
        $payment = Payment::create([
            'external_id' => 'PAY_TEST123',
            'amount' => 100.00,
            'currency' => 'BRL',
            'provider' => 'ProviderA',
            'status' => 'pending'
        ]);

        $webhookData = [
            'payment_id' => 'PAY_TEST123',
            'status' => 'completed',
            'id' => 'webhook_123',
            'type' => 'payment.completed'
        ];

        $response = $this->postJson('/api/webhooks/payment/providerA', $webhookData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
    }

    public function test_webhook_idempotency(): void
    {
        
        $payment = Payment::create([
            'external_id' => 'PAY_TEST456',
            'amount' => 100.00,
            'currency' => 'BRL',
            'provider' => 'ProviderA',
            'status' => 'pending'
        ]);

        $webhookData = [
            'payment_id' => 'PAY_TEST456',
            'status' => 'completed',
            'id' => 'webhook_456',
            'type' => 'payment.completed'
        ];

        
        $response1 = $this->postJson('/api/webhooks/payment/providerA', $webhookData);
        $response2 = $this->postJson('/api/webhooks/payment/providerA', $webhookData);

        
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
    }
}
