<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use DatabaseTransactions;

    private $admin;
    private $adminToken;
    private $managerToken;
    private $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'ADMIN']);
        $this->adminToken = $this->admin->createToken('test-token')->plainTextToken;

        $manager = User::factory()->create(['role' => 'MANAGER']);
        $this->managerToken = $manager->createToken('test-token')->plainTextToken;

        $user = User::factory()->create(['role' => 'USER']);
        $this->userToken = $user->createToken('test-token')->plainTextToken;
    }

    
    public function admin_can_list_users()
    {
        User::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/users');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => ['id', 'name', 'email', 'role']
                    ]
                ]);
    }

    
    public function admin_can_create_user()
    {
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'USER'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->postJson('/api/users', $userData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Usuário criado com sucesso'
                ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role' => 'USER'
        ]);
    }

    
    public function admin_can_update_user()
    {
        $user = User::factory()->create(['role' => 'USER']);

        $updateData = [
            'name' => 'Updated Name',
            'role' => 'MANAGER'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->putJson("/api/users/{$user->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Usuário atualizado com sucesso'
                ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'role' => 'MANAGER'
        ]);
    }

    
    public function admin_can_delete_user()
    {
        $user = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Usuário deletado com sucesso'
                ]);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    
    public function user_cannot_delete_own_account()
    {
        // Criar um usuário ADMIN separado para tentar deletar a si mesmo
        $selfDeletingAdmin = User::factory()->create(['role' => 'ADMIN']);
        $selfDeletingToken = $selfDeletingAdmin->createToken('self-delete-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $selfDeletingToken
        ])->deleteJson("/api/users/{$selfDeletingAdmin->id}");

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Você não pode deletar sua própria conta'
                ]);
    }

    
    public function non_admin_cannot_manage_users()
    {
        // Manager não pode gerenciar usuários
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->managerToken
        ])->getJson('/api/users');

        $response->assertStatus(403);

        // User não pode gerenciar usuários
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->userToken
        ])->getJson('/api/users');

        $response->assertStatus(403);
    }

    private function getAdminUserId()
    {
        $token = explode('|', $this->adminToken)[0];
        return \Laravel\Sanctum\PersonalAccessToken::findToken($token)->tokenable_id;
    }
}