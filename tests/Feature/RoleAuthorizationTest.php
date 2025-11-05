<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Gateway;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function admin_can_access_all_routes()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/users');

        $response->assertStatus(200);
    }

    /** @test */
    public function manager_can_manage_products_but_not_users()
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

        // Manager NÃO PODE gerenciar usuários
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/users');

        $response->assertStatus(403);
    }

    /** @test */
    public function finance_can_process_refunds_but_not_manage_products()
    {
        $finance = User::factory()->create(['role' => 'FINANCE']);
        $token = $finance->createToken('test-token')->plainTextToken;

        // Criar uma transação para reembolso
        $transaction = Transaction::factory()->create(['status' => 'approved']);

        // Finance PODE processar reembolsos
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/transactions/{$transaction->id}/refund");

        $response->assertStatus(200);

        // Finance NÃO PODE gerenciar produtos
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/products', [
            'name' => 'New Product',
            'amount' => 10000
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_only_access_basic_routes()
    {
        $user = User::factory()->create(['role' => 'USER']);
        $token = $user->createToken('test-token')->plainTextToken;

        // User PODE listar transações
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/transactions');

        $response->assertStatus(200);

        // User NÃO PODE gerenciar usuários
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/users');

        $response->assertStatus(403);

        // User NÃO PODE gerenciar produtos
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/products', [
            'name' => 'New Product',
            'amount' => 10000
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_protected_routes()
    {
        $response = $this->getJson('/api/users');
        $response->assertStatus(401);

        $response = $this->getJson('/api/transactions');
        $response->assertStatus(401);
    }
}