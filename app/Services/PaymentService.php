<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Processa um pagamento através do sistema multi-gateway
     * 
     * Este método implementa a estratégia de fallback entre gateways de pagamento.
     * Tenta processar o pagamento sequencialmente pelos gateways ativos ordenados por prioridade.
     * Se um gateway falhar, automaticamente tenta o próximo até esgotar todas as opções.
     * 
     * Fluxo:
     * 1. Busca gateways ativos ordenados por prioridade
     * 2. Para cada gateway, tenta processar o pagamento
     * 3. No primeiro sucesso, cria transação aprovada e retorna
     * 4. Se todos falharem, cria transação rejeitada e retorna erro
     * 
     * @param array $paymentData Dados do pagamento (amount, card_number, cvv, client_name, client_email)
     * @param array $products Lista de produtos com id, quantity e unit_amount
     * @return array Resultado do processamento com sucesso/erro e dados da transação
     */
    public function processPayment(array $paymentData, array $products = [])
    {
        // Busca gateways ativos ordenados por prioridade (menor número = maior prioridade)
        $gateways = Gateway::active()->byPriority()->get();
        $lastError = null;

        // Itera por cada gateway na ordem de prioridade
        foreach ($gateways as $gateway) {
            try {
                // Tenta processar com o gateway atual
                $result = $this->processWithGateway($gateway, $paymentData);
                
                // Se sucesso, cria transação e retorna imediatamente
                if ($result['success']) {
                    return $this->createSuccessfulTransaction($gateway, $paymentData, $result, $products);
                }
                
                // Se falha, armazena erro e continua para próximo gateway
                $lastError = $result;
            } catch (\Exception $e) {
                // Loga erro excepcional e continua para próximo gateway
                Log::error("Gateway {$gateway->name} error: " . $e->getMessage());
                $lastError = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        // Todos os gateways falharam - cria transação rejeitada
        return $this->createFailedTransaction($paymentData, $lastError, $products);
    }

    /**
     * Roteia o processamento para o método específico de cada gateway
     * 
     * Utiliza match expression para direcionar para a implementação correta
     * baseada no nome do gateway. Facilita a adição de novos gateways futuros.
     * 
     * @param Gateway $gateway Instância do gateway a ser utilizado
     * @param array $paymentData Dados do pagamento
     * @return array Resultado do processamento pelo gateway específico
     * @throws \Exception Se o gateway não for suportado
     */
    private function processWithGateway(Gateway $gateway, array $paymentData)
    {
        return match($gateway->name) {
            'Gateway 1' => $this->processGateway1($paymentData),
            'Gateway 2' => $this->processGateway2($paymentData),
            default => throw new \Exception("Gateway não suportado: {$gateway->name}"),
        };
    }

    /**
     * Processa pagamento através do Gateway 1
     * 
     * Gateway 1 requer autenticação via token JWT antes do processamento.
     * Fluxo: Login → Obter token → Processar pagamento com token
     * 
     * Estrutura esperada do Gateway 1:
     * - Login: POST /login {email, token} → retorna access_token
     * - Pagamento: POST /transactions {amount, name, email, cardNumber, cvv}
     * 
     * @param array $paymentData Dados do pagamento
     * @return array Resultado com sucesso, external_id e resposta completa
     * @throws \Exception Se falhar no login ou processamento
     */
    private function processGateway1(array $paymentData)
    {
        // Fase 1: Autenticação no Gateway 1
        $loginResponse = Http::post(config('services.gateway1.base_url') . '/login', [
            'email' => config('services.gateway1.email'),
            'token' => config('services.gateway1.token'),
        ]);

        if (!$loginResponse->successful()) {
            throw new \Exception('Falha no login Gateway 1');
        }

        $token = $loginResponse->json('token');

        // Fase 2: Processamento do pagamento com token de autenticação
        $paymentResponse = Http::withToken($token)->post(
            config('services.gateway1.base_url') . '/transactions',
            [
                'amount' => $paymentData['amount'],        // Valor em centavos
                'name' => $paymentData['client_name'],     // Nome do cliente
                'email' => $paymentData['client_email'],   // Email do cliente
                'cardNumber' => $paymentData['card_number'], // Número do cartão (16 dígitos)
                'cvv' => $paymentData['cvv'],              // Código de segurança
            ]
        );

        if ($paymentResponse->successful()) {
            return [
                'success' => true,
                'external_id' => $paymentResponse->json('id'), // ID único do gateway
                'gateway_response' => $paymentResponse->json(), // Resposta completa para auditoria
            ];
        }

        // Pagamento recusado pelo gateway
        return [
            'success' => false,
            'error' => $paymentResponse->json('message', 'Erro desconhecido'),
            'gateway_response' => $paymentResponse->json(),
        ];
    }

    /**
     * Processa pagamento através do Gateway 2
     * 
     * Gateway 2 utiliza autenticação via headers com token e secret.
     * Fluxo: Headers de autenticação → Processar pagamento
     * 
     * Estrutura esperada do Gateway 2:
     * - Autenticação: Headers Gateway-Auth-Token e Gateway-Auth-Secret
     * - Pagamento: POST /transacoes {valor, nome, email, numeroCartao, cvv}
     * 
     * @param array $paymentData Dados do pagamento
     * @return array Resultado com sucesso, external_id e resposta completa
     */
    private function processGateway2(array $paymentData)
    {
        $response = Http::withHeaders([
            'Gateway-Auth-Token' => config('services.gateway2.token'),
            'Gateway-Auth-Secret' => config('services.gateway2.secret'),
        ])->post(config('services.gateway2.base_url') . '/transacoes', [
            'valor' => $paymentData['amount'],             // Valor em centavos
            'nome' => $paymentData['client_name'],         // Nome do cliente
            'email' => $paymentData['client_email'],       // Email do cliente
            'numeroCartao' => $paymentData['card_number'], // Número do cartão
            'cvv' => $paymentData['cvv'],                  // Código de segurança
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'external_id' => $response->json('id'),    // ID único do gateway
                'gateway_response' => $response->json(),   // Resposta completa para auditoria
            ];
        }

        // Pagamento recusado pelo gateway
        return [
            'success' => false,
            'error' => $response->json('mensagem', 'Erro desconhecido'),
            'gateway_response' => $response->json(),
        ];
    }

    /**
     * Cria uma transação de sucesso no banco de dados
     * 
     * Após um pagamento ser aprovado por um gateway, esta função:
     * 1. Cria/recupera o cliente
     * 2. Cria a transação com status 'approved'
     * 3. Associa os produtos à transação via pivot table
     * 
     * @param Gateway $gateway Gateway que processou com sucesso
     * @param array $paymentData Dados originais do pagamento
     * @param array $result Resultado do processamento do gateway
     * @param array $products Produtos adquiridos
     * @return array Resposta de sucesso para o cliente
     */
    private function createSuccessfulTransaction($gateway, $paymentData, $result, $products)
    {
        // Busca ou cria cliente baseado no email (evita duplicação)
        $client = Client::firstOrCreate(
            ['email' => $paymentData['client_email']],
            ['name' => $paymentData['client_name']]
        );

        // Cria transação aprovada
        $transaction = Transaction::create([
            'client_id' => $client->id,
            'gateway_id' => $gateway->id,                  // Gateway que processou
            'external_id' => $result['external_id'],       // ID no gateway externo
            'status' => 'approved',                        // Status de aprovação
            'amount' => $paymentData['amount'],            // Valor total em centavos
            'card_last_numbers' => substr($paymentData['card_number'], -4), // Últimos 4 dígitos (PCI compliance)
            'gateway_response' => $result['gateway_response'], // Resposta completa para debug/auditoria
        ]);

        // Associa produtos à transação via pivot table
        foreach ($products as $product) {
            $transaction->products()->attach($product['id'], [
                'quantity' => $product['quantity'],        // Quantidade comprada
                'unit_amount' => $product['unit_amount'],  // Preço unitário em centavos
            ]);
        }

        return [
            'success' => true,
            'transaction_id' => $transaction->id,          // ID interno da transação
            'gateway_used' => $gateway->name,              // Gateway que processou
            'status' => 'approved',                        // Status final
        ];
    }

    /**
     * Cria uma transação de falha no banco de dados
     * 
     * Quando todos os gateways falham, esta função cria uma transação
     * com status 'rejected' para manter histórico completo.
     * 
     * @param array $paymentData Dados originais do pagamento
     * @param array $error Último erro ocorrido
     * @param array $products Produtos que seriam adquiridos
     * @return array Resposta de erro para o cliente
     */
    private function createFailedTransaction($paymentData, $error, $products)
    {
        // Busca ou cria cliente mesmo em caso de falha
        $client = Client::firstOrCreate(
            ['email' => $paymentData['client_email']],
            ['name' => $paymentData['client_name']]
        );

        // Cria transação rejeitada
        $transaction = Transaction::create([
            'client_id' => $client->id,
            'gateway_id' => null,                          // Nenhum gateway processou
            'status' => 'rejected',                        // Status de rejeição
            'amount' => $paymentData['amount'],            // Valor que seria cobrado
            'card_last_numbers' => substr($paymentData['card_number'], -4), // Últimos 4 dígitos
            'gateway_response' => $error,                  // Último erro ocorrido
        ]);

        // Associa produtos mesmo em transação rejeitada (para histórico)
        foreach ($products as $product) {
            $transaction->products()->attach($product['id'], [
                'quantity' => $product['quantity'],
                'unit_amount' => $product['unit_amount'],
            ]);
        }

        return [
            'success' => false,
            'error' => $error['error'] ?? 'Todos os gateways falharam',
            'transaction_id' => $transaction->id,          // ID para referência
        ];
    }

    /**
     * Processa reembolso através do Gateway 1
     * 
     * Realiza o reembolso de uma transação previamente aprovada no Gateway 1.
     * Segue o mesmo fluxo de autenticação do processamento original.
     * 
     * @param Transaction $transaction Transação a ser reembolsada
     * @return array Resposta do gateway
     * @throws \Exception Se falhar na autenticação ou reembolso
     */
    public function refundGateway1(Transaction $transaction)
    {
        // Autenticação no Gateway 1 (mesmo processo do pagamento)
        $loginResponse = Http::post(config('services.gateway1.base_url') . '/login', [
            'email' => config('services.gateway1.email'),
            'token' => config('services.gateway1.token'),
        ]);

        if (!$loginResponse->successful()) {
            throw new \Exception('Falha no login Gateway 1 para reembolso');
        }

        $token = $loginResponse->json('token');

        // Processa reembolso usando o external_id da transação original
        $response = Http::withToken($token)->post(
            config('services.gateway1.base_url') . "/transactions/{$transaction->external_id}/charge_back"
        );

        if (!$response->successful()) {
            throw new \Exception('Falha no reembolso Gateway 1: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Processa reembolso através do Gateway 2
     * 
     * Realiza o reembolso de uma transação previamente aprovada no Gateway 2.
     * Utiliza a mesma autenticação por headers do processamento original.
     * 
     * @param Transaction $transaction Transação a ser reembolsada
     * @return array Resposta do gateway
     * @throws \Exception Se falhar no reembolso
     */
    public function refundGateway2(Transaction $transaction)
    {
        $response = Http::withHeaders([
            'Gateway-Auth-Token' => config('services.gateway2.token'),
            'Gateway-Auth-Secret' => config('services.gateway2.secret'),
        ])->post(config('services.gateway2.base_url') . '/transacoes/reembolso', [
            'id' => $transaction->external_id // ID original no Gateway 2
        ]);

        if (!$response->successful()) {
            throw new \Exception('Falha no reembolso Gateway 2: ' . $response->body());
        }

        return $response->json();
    }
}