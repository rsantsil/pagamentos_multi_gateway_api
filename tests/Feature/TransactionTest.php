<?php

namespace Tests\Feature;

use App\Models\Transaction;
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
    private $product;

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

        // Binding correto no container Laravel
        $this->app->instance(PaymentService::class, $this->paymentServiceMock);

        // Debug: verificar binding
        $bound = app()->bound(PaymentService::class);
        $instance = app(PaymentService::class);
    }

    
    
    public function user_can_make_purchase()
    {
        // Arrange
        $product = Product::factory()->create([
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
            }), Mockery::on(function ($products) use ($product) {
                return $products[0]['id'] === $product->id && $products[0]['quantity'] === 1;
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
                ['id' => $product->id, 'quantity' => 1] 
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

    
    
    public function purchase_fails_when_payment_service_fails()
    {
        // Arrange
        $product = Product::factory()->create([
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
                ['id' => $product->id, 'quantity' => 1] 
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

    
    public function finance_user_can_refund_transaction()
    {
        // Arrange
        $financeUser = User::factory()->create(['role' => 'FINANCE']);
        $token = $financeUser->createToken('test-token')->plainTextToken;

        $gateway = Gateway::factory()->create(['name' => 'Gateway 1']);
        $transaction = Transaction::factory()->create([
            'status' => 'approved',
            'gateway_id' => $gateway->id,
            'external_id' => 'test_123'
        ]);

        // Mock do refundGateway1
        $this->paymentServiceMock
            ->shouldReceive('refundGateway1')
            ->once()
            ->with(Mockery::type(Transaction::class))
            ->andReturn(['success' => true]);

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/transactions/{$transaction->id}/refund");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Transação reembolsada com sucesso'
            ]);
    }

    
    public function user_cannot_refund_transaction()
    {
        // Arrange
        $regularUser = User::factory()->create(['role' => 'USER']);
        $token = $regularUser->createToken('test-token')->plainTextToken;

        $transaction = Transaction::factory()->create(['status' => 'approved']);

        // Mock - não deve ser chamado
        $this->paymentServiceMock
            ->shouldReceive('refundGateway1')
            ->never();

        $this->paymentServiceMock
            ->shouldReceive('refundGateway2')
            ->never();

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/transactions/{$transaction->id}/refund");

        // Assert
        $response->assertStatus(403);
    }

    
    public function cannot_refund_non_approved_transaction()
    {
        // Arrange
        $financeUser = User::factory()->create(['role' => 'FINANCE']);
        $token = $financeUser->createToken('test-token')->plainTextToken;

        $transaction = Transaction::factory()->create(['status' => 'rejected']);

        // Mock - não deve ser chamado
        $this->paymentServiceMock
            ->shouldReceive('refundGateway1')
            ->never();

        $this->paymentServiceMock
            ->shouldReceive('refundGateway2')
            ->never();

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/transactions/{$transaction->id}/refund");

        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Apenas transações aprovadas podem ser reembolsadas'
            ]);
    }

    
    public function admin_can_refund_transaction()
    {
        // Arrange
        $adminUser = User::factory()->create(['role' => 'ADMIN']);
        $token = $adminUser->createToken('test-token')->plainTextToken;

        $gateway = Gateway::factory()->create(['name' => 'Gateway 2']);
        $transaction = Transaction::factory()->create([
            'status' => 'approved',
            'gateway_id' => $gateway->id,
            'external_id' => 'test_456'
        ]);

        // Mock do refundGateway2
        $this->paymentServiceMock
            ->shouldReceive('refundGateway2')
            ->once()
            ->with(Mockery::type(Transaction::class))
            ->andReturn(['success' => true]);

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/transactions/{$transaction->id}/refund");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Transação reembolsada com sucesso'
            ]);
    }

    
    
    
    
    public function finance_can_manage_products_and_process_refunds()
    {
        $finance = User::factory()->create(['role' => 'FINANCE']);
        $token = $finance->createToken('test-token')->plainTextToken;

        // Criar uma transação APROVADA para reembolso
        $gateway = Gateway::factory()->create(['name' => 'Gateway 1']);
        $transaction = Transaction::factory()->create([
            'status' => 'approved',
            'gateway_id' => $gateway->id,
            'external_id' => 'test_123'
        ]);

        // Mock para o reembolso 
        // O TransactionController chama refundGateway1/refundGateway2 diretamente
        $this->paymentServiceMock
            ->shouldReceive('refundGateway1')
            ->once()
            ->with(Mockery::on(function ($transactionArg) use ($transaction) {
                return $transactionArg->id === $transaction->id;
            }))
            ->andReturn(['success' => true, 'message' => 'Refund processed']);

        // Finance PODE processar reembolsos
        $refundResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/transactions/{$transaction->id}/refund");

        // Verificar se não é erro 500
        if ($refundResponse->status() === 500) {
            $content = $refundResponse->getContent();
            $this->fail("Erro 500 no reembolso: " . $content);
        }

        $refundResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Transação reembolsada com sucesso'
            ]);

        // Finance NÃO PODE criar produtos
        $productResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/products', [
            'name' => 'New Product',
            'amount' => 10000,
            'description' => 'Test description'
        ]);

        $productResponse->assertStatus(403);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
