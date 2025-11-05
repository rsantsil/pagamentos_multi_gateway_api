<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function processPayment(array $paymentData, array $products = [])
    {
        $gateways = Gateway::active()->byPriority()->get();
        $lastError = null;

        foreach ($gateways as $gateway) {
            try {
                $result = $this->processWithGateway($gateway, $paymentData);
                
                if ($result['success']) {
                    return $this->createSuccessfulTransaction($gateway, $paymentData, $result, $products);
                }
                
                $lastError = $result;
            } catch (\Exception $e) {
                Log::error("Gateway {$gateway->name} error: " . $e->getMessage());
                $lastError = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $this->createFailedTransaction($paymentData, $lastError, $products);
    }

    private function processWithGateway(Gateway $gateway, array $paymentData)
    {
        return match($gateway->name) {
            'Gateway 1' => $this->processGateway1($paymentData),
            'Gateway 2' => $this->processGateway2($paymentData),
            default => throw new \Exception("Gateway não suportado: {$gateway->name}"),
        };
    }

    private function processGateway1(array $paymentData)
    {
        // Login primeiro
        $loginResponse = Http::post(config('services.gateway1.base_url') . '/login', [
            'email' => config('services.gateway1.email'),
            'token' => config('services.gateway1.token'),
        ]);

        if (!$loginResponse->successful()) {
            throw new \Exception('Falha no login Gateway 1');
        }

        $token = $loginResponse->json('token');

        // Processar pagamento
        $paymentResponse = Http::withToken($token)->post(
            config('services.gateway1.base_url') . '/transactions',
            [
                'amount' => $paymentData['amount'],
                'name' => $paymentData['client_name'],
                'email' => $paymentData['client_email'],
                'cardNumber' => $paymentData['card_number'],
                'cvv' => $paymentData['cvv'],
            ]
        );

        if ($paymentResponse->successful()) {
            return [
                'success' => true,
                'external_id' => $paymentResponse->json('id'),
                'gateway_response' => $paymentResponse->json(),
            ];
        }

        return [
            'success' => false,
            'error' => $paymentResponse->json('message', 'Erro desconhecido'),
            'gateway_response' => $paymentResponse->json(),
        ];
    }

    private function processGateway2(array $paymentData)
    {
        $response = Http::withHeaders([
            'Gateway-Auth-Token' => config('services.gateway2.token'),
            'Gateway-Auth-Secret' => config('services.gateway2.secret'),
        ])->post(config('services.gateway2.base_url') . '/transacoes', [
            'valor' => $paymentData['amount'],
            'nome' => $paymentData['client_name'],
            'email' => $paymentData['client_email'],
            'numeroCartao' => $paymentData['card_number'],
            'cvv' => $paymentData['cvv'],
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'external_id' => $response->json('id'),
                'gateway_response' => $response->json(),
            ];
        }

        return [
            'success' => false,
            'error' => $response->json('mensagem', 'Erro desconhecido'),
            'gateway_response' => $response->json(),
        ];
    }

    private function createSuccessfulTransaction($gateway, $paymentData, $result, $products)
    {
        // Criar ou encontrar cliente
        $client = Client::firstOrCreate(
            ['email' => $paymentData['client_email']],
            ['name' => $paymentData['client_name']]
        );

        // Criar transação
        $transaction = Transaction::create([
            'client_id' => $client->id,
            'gateway_id' => $gateway->id,
            'external_id' => $result['external_id'],
            'status' => 'approved',
            'amount' => $paymentData['amount'],
            'card_last_numbers' => substr($paymentData['card_number'], -4),
            'gateway_response' => $result['gateway_response'],
        ]);

        // Adicionar produtos à transação
        foreach ($products as $product) {
            $transaction->products()->attach($product['id'], [
                'quantity' => $product['quantity'],
                'unit_amount' => $product['unit_amount'],
            ]);
        }

        return [
            'success' => true,
            'transaction_id' => $transaction->id,
            'gateway_used' => $gateway->name,
            'status' => 'approved',
        ];
    }

    private function createFailedTransaction($paymentData, $error, $products)
    {
        $client = Client::firstOrCreate(
            ['email' => $paymentData['client_email']],
            ['name' => $paymentData['client_name']]
        );

        $transaction = Transaction::create([
            'client_id' => $client->id,
            'gateway_id' => null,
            'status' => 'rejected',
            'amount' => $paymentData['amount'],
            'card_last_numbers' => substr($paymentData['card_number'], -4),
            'gateway_response' => $error,
        ]);

        foreach ($products as $product) {
            $transaction->products()->attach($product['id'], [
                'quantity' => $product['quantity'],
                'unit_amount' => $product['unit_amount'],
            ]);
        }

        return [
            'success' => false,
            'error' => $error['error'] ?? 'Todos os gateways falharam',
            'transaction_id' => $transaction->id,
        ];
    }
}