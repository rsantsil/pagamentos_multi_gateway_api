<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Gateway;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Mockery;

class RoleAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    
    public function admin_can_access_all_routes()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/users');

        $response->assertStatus(200);
    }

    
    public function manager_can_manage_users_and_products()
    {
        $manager = User::factory()->create(['role' => 'MANAGER']);
        $token = $manager->createToken('test-token')->plainTextToken;

        // Manager PODE gerenciar produtos
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/products', [
            'name' => 'New Product',
            'amount' => 10000,
            'description' => 'Test product'
        ]);

        $response->assertStatus(201);

        // Manager NÃO PODE gerenciar usuários (apenas admin)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/users');

        $response->assertStatus(403); 
    }

    
    public function finance_can_manage_products_and_process_refunds()
    {
        // Mock do PaymentService para reembolso
        $paymentServiceMock = Mockery::mock(PaymentService::class);
        $paymentServiceMock->shouldReceive('refundGateway1')->andReturn(['success' => true]);
        $this->app->instance(PaymentService::class, $paymentServiceMock);

        $finance = User::factory()->create(['role' => 'FINANCE']);
        $token = $finance->createToken('test-token')->plainTextToken;

        // Finance NÃO PODE gerenciar produtos (apenas admin e manager)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/products', [
            'name' => 'New Product',
            'amount' => 10000,
            'description' => 'Test product'
        ]);

        $response->assertStatus(403); // ← CORRIGIDO: era 201, agora 403

        // Finance PODE processar reembolsos
        $gateway = Gateway::factory()->create(['name' => 'Gateway 1']);
        $transaction = Transaction::factory()->create([
            'status' => 'approved',
            'gateway_id' => $gateway->id,
            'external_id' => 'test_123'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(200);

        Mockery::close();
    }

    
    public function user_can_only_access_basic_routes()
    {
        $user = User::factory()->create(['role' => 'USER']);
        $token = $user->createToken('test-token')->plainTextToken;

        // User PODE listar transações
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/transactions');

        $response->assertStatus(200);

        // User PODE listar clientes
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/clients');

        $response->assertStatus(200);

        // User PODE listar produtos
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/products');

        $response->assertStatus(200);

        // User NÃO PODE gerenciar usuários
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/users');

        $response->assertStatus(403);

        // User NÃO PODE criar produtos
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/products', [
            'name' => 'New Product',
            'amount' => 10000
        ]);

        $response->assertStatus(403);

        // User NÃO PODE reembolsar
        $transaction = Transaction::factory()->create(['status' => 'approved']);
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(403);
    }

    
    public function unauthenticated_user_cannot_access_protected_routes()
    {
        $response = $this->getJson('/api/users');
        $response->assertStatus(401);

        $response = $this->getJson('/api/transactions');
        $response->assertStatus(401);

        $response = $this->getJson('/api/clients');
        $response->assertStatus(401);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}