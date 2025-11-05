<?php

namespace Tests\Unit\Services;

use App\Models\Gateway;
use App\Models\Product;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = new PaymentService();
        
        // Criar gateways de teste
        Gateway::factory()->create(['name' => 'Gateway 1', 'priority' => 1, 'is_active' => true]);
        Gateway::factory()->create(['name' => 'Gateway 2', 'priority' => 2, 'is_active' => true]);
        
        // Criar produto de teste
        Product::factory()->create(['id' => 1, 'amount' => 10000]);
    }

    /** @test */
    public function it_can_process_payment_successfully()
    {
        // Arrange
        $paymentData = [
            'amount' => 10000,
            'card_number' => '5569000000006063',
            'cvv' => '123',
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com'
        ];

        $products = [
            ['id' => 1, 'quantity' => 1, 'unit_amount' => 10000]
        ];

        // Act
        $result = $this->paymentService->processPayment($paymentData, $products);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('approved', $result['status']);
        $this->assertArrayHasKey('transaction_id', $result);
    }

    /** @test */
    public function it_falls_back_to_second_gateway_when_first_fails()
    {
        // Arrange
        $paymentData = [
            'amount' => 10000,
            'card_number' => '5569000000006063',
            'cvv' => '100', // CVV que causa erro no Gateway 1
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com'
        ];

        $products = [
            ['id' => 1, 'quantity' => 1, 'unit_amount' => 10000]
        ];

        // Act
        $result = $this->paymentService->processPayment($paymentData, $products);

        // Assert - Deve tentar o Gateway 2
        $this->assertTrue($result['success']);
        $this->assertEquals('approved', $result['status']);
    }

    /** @test */
    public function it_creates_client_when_not_exists()
    {
        // Arrange
        $paymentData = [
            'amount' => 10000,
            'card_number' => '5569000000006063',
            'cvv' => '123',
            'client_name' => 'New Client',
            'client_email' => 'newclient@example.com'
        ];

        $products = [
            ['id' => 1, 'quantity' => 1, 'unit_amount' => 10000]
        ];

        // Act
        $result = $this->paymentService->processPayment($paymentData, $products);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('clients', [
            'email' => 'newclient@example.com',
            'name' => 'New Client'
        ]);
    }
}