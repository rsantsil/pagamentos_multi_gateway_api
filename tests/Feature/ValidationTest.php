<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    use DatabaseTransactions;

    
    public function purchase_validates_required_fields()
    {
        $response = $this->postJson('/api/purchase', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'products', 'card_number', 'cvv', 'client_name', 'client_email'
                ]);
    }

    
    public function purchase_validates_card_number_format()
    {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/purchase', [
            'products' => [['id' => $product->id, 'quantity' => 1]],
            'card_number' => '123', // Inválido
            'cvv' => '123',
            'client_name' => 'Test User',
            'client_email' => 'test@example.com'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['card_number']);
    }

    
    public function purchase_validates_email_format()
    {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/purchase', [
            'products' => [['id' => $product->id, 'quantity' => 1]],
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'client_name' => 'Test User',
            'client_email' => 'invalid-email' // Inválido
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['client_email']);
    }

    
    public function user_creation_validates_required_fields()
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/users', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    
    public function user_creation_validates_unique_email()
    {
        $existingUser = User::factory()->create();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/users', [
            'name' => 'New User',
            'email' => $existingUser->email, // Email já existe
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'USER'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }
}