<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Gateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GatewayControllerTest extends TestCase
{
    use DatabaseTransactions;

    private $adminToken;
    private $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->adminToken = $admin->createToken('test-token')->plainTextToken;

        $user = User::factory()->create(['role' => 'USER']);
        $this->userToken = $user->createToken('test-token')->plainTextToken;
    }

    /** @test */
    public function anyone_can_list_gateways()
    {
        Gateway::factory()->count(2)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken
        ])->getJson('/api/gateways');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => ['id', 'name', 'is_active', 'priority']
                    ]
                ]);
    }

    /** @test */
    public function admin_can_toggle_gateway_status()
    {
        $gateway = Gateway::factory()->create(['is_active' => true]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->patchJson("/api/gateways/{$gateway->id}/toggle");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Gateway desativado com sucesso'
                ]);

        $this->assertDatabaseHas('gateways', [
            'id' => $gateway->id,
            'is_active' => false
        ]);
    }

    /** @test */
    public function admin_can_update_gateway_priority()
    {
        $gateway = Gateway::factory()->create(['priority' => 1]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->patchJson("/api/gateways/{$gateway->id}/priority", [
            'priority' => 5
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Prioridade do gateway atualizada com sucesso'
                ]);

        $this->assertDatabaseHas('gateways', [
            'id' => $gateway->id,
            'priority' => 5
        ]);
    }

    /** @test */
    public function non_admin_cannot_manage_gateways()
    {
        $gateway = Gateway::factory()->create();

        // User não pode toggle gateway
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken
        ])->patchJson("/api/gateways/{$gateway->id}/toggle");

        $response->assertStatus(403);

        // User não pode atualizar prioridade
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken
        ])->patchJson("/api/gateways/{$gateway->id}/priority", [
            'priority' => 5
        ]);

        $response->assertStatus(403);
    }
}