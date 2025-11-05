<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Gateway;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Mockery;

class TransactionTest extends TestCase
{
    use DatabaseTransactions;

    private $paymentServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Criar gateways
        Gateway::factory()->create([
            'name' => 'Gateway 1', 
            'priority' => 1, 
            'is_active' => true
        ]);
        
        Gateway::factory()->create([
            'name' => 'Gateway 2', 
            'priority' => 2, 
            'is_active' => true
        ]);

        // Mock do PaymentService
        $this->paymentServiceMock = Mockery::mock(PaymentService::class);
        $this->app->instance(PaymentService::class, $this->paymentServiceMock);
    }

    /** @test */
    public function user_can_make_purchase()
    {
        // Arrange
        $product = Product::factory()->create([
            'id' => 1, 
            'amount' => 10000,
            'name' => 'Test Product'
        ]);

        // Mock do processPayment para retornar sucesso
        $this->paymentServiceMock
            ->shouldReceive('processPayment')
            ->once()
            ->with(Mockery::on(function ($paymentData) {
                return $paymentData['amount'] === 10000 &&
                       $paymentData['card_number'] === '4111111111111111';
            }), Mockery::on(function ($products) {
                return $products[0]['id'] === 1 && $products[0]['quantity'] === 1;
            }))
            ->andReturn([
                'success' => true,
                'transaction_id' => 1,
                'gateway_used' => 'Gateway 1',
                'status' => 'approved'
            ]);

        // Act
        $response = $this->postJson('/api/purchase', [
            'products' => [
                ['id' => 1, 'quantity' => 1]
            ],
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'client_name' => 'Test User',
            'client_email' => 'test@example.com'
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'status' => 'approved'
                ])
                ->assertJsonStructure([
                    'success',
                    'transaction_id',
                    'gateway_used',
                    'status'
                ]);
    }

    /** @test */
    public function purchase_fails_with_invalid_product()
    {
        // Mock para este teste específico
        $this->paymentServiceMock
            ->shouldReceive('processPayment')
            ->never();

        // Act
        $response = $this->postJson('/api/purchase', [
            'products' => [
                ['id' => 999, 'quantity' => 1]
            ],
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'client_name' => 'Test User',
            'client_email' => 'test@example.com'
        ]);

        // Assert - Deve falhar na validação antes de chegar no PaymentService
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['products.0.id']);
    }

    /** @test */
    public function purchase_fails_when_payment_service_fails()
    {
        // Arrange
        $product = Product::factory()->create([
            'id' => 1, 
            'amount' => 10000
        ]);

        // Mock do processPayment para retornar falha
        $this->paymentServiceMock
            ->shouldReceive('processPayment')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Todos os gateways falharam',
                'transaction_id' => 2
            ]);

        // Act
        $response = $this->postJson('/api/purchase', [
            'products' => [
                ['id' => 1, 'quantity' => 1]
            ],
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'client_name' => 'Test User',
            'client_email' => 'test@example.com'
        ]);

        // Assert
        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'error' => 'Todos os gateways falharam'
                ]);
    }

    /** @test */
    public function authenticated_user_can_list_transactions()
    {
        // Arrange
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/transactions');

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'pagination'
                ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}