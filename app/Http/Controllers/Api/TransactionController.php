<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    private $paymentService;

    /**
     * Injeção de dependência do PaymentService
     * 
     * O PaymentService é responsável por toda a lógica de processamento
     * de pagamentos e comunicação com os gateways externos.
     * 
     * @param PaymentService $paymentService
     */
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Exibe os detalhes completos de uma transação específica
     * 
     * Este endpoint retorna informações detalhadas de uma transação,
     * incluindo dados do cliente, gateway utilizado e produtos adquiridos.
     * 
     * Acesso: Todos os usuários autenticados
     * Eager Loading: Carrega relacionamentos para performance
     * 
     * @param Transaction $transaction Instância via Route Model Binding
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Transaction $transaction)
    {
        // Carrega todos os relacionamentos para exibição completa
        $transaction->load(['client', 'gateway', 'products']);

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }

    /**
     * Processa uma nova compra/pagamento
     * 
     * Este endpoint é público e processa pagamentos através do sistema
     * multi-gateway. Realiza validação, cálculo do total e delega o
     * processamento para o PaymentService.
     * 
     * Acesso: Público (não requer autenticação)
     * Fluxo: Validação → Cálculo → Processamento → Resposta
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function purchase(Request $request)
    {
        // Validação customizada usando Validator para melhor controle
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|min:1',                           // Pelo menos 1 produto
            'products.*.id' => 'required|exists:products,id',              // Produto deve existir
            'products.*.quantity' => 'required|integer|min:1',             // Quantidade mínima 1
            'card_number' => 'required|string|size:16',                    // Número do cartão (16 dígitos)
            'cvv' => 'required|string|min:3|max:4',                        // CVV (3-4 dígitos)
            'client_name' => 'required|string|max:255',                    // Nome do cliente
            'client_email' => 'required|email',                            // Email válido
        ]);

        // Retorna erros de validação de forma estruturada
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422); // HTTP 422 - Unprocessable Entity
        }

        // Cálculo do valor total e preparação dos dados dos produtos
        $totalAmount = 0;
        $productsData = [];

        foreach ($request->products as $item) {
            // Busca cada produto para obter o preço atual
            $product = \App\Models\Product::find($item['id']);
            
            // Soma ao total (amount está em centavos)
            $totalAmount += $product->amount * $item['quantity'];
            
            // Prepara dados para o PaymentService e histórico
            $productsData[] = [
                'id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_amount' => $product->amount, // Preço unitário em centavos
            ];
        }

        // Prepara dados para o gateway de pagamento
        $paymentData = [
            'amount' => $totalAmount,              // Total em centavos
            'card_number' => $request->card_number,
            'cvv' => $request->cvv,
            'client_name' => $request->client_name,
            'client_email' => $request->client_email,
        ];

        // Delega o processamento para o PaymentService
        // O service implementa a lógica de tentativa com múltiplos gateways
        $result = $this->paymentService->processPayment($paymentData, $productsData);

        // Retorna resposta com status apropriado baseado no resultado
        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Lista todas as transações com paginação
     * 
     * Este endpoint retorna um histórico paginado de todas as transações
     * do sistema, ordenado pelas mais recentes primeiro.
     * 
     * Acesso: Todos os usuários autenticados
     * Paginação: 10 registros por página por padrão
     * Eager Loading: Otimiza queries relacionadas
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Busca transações com relacionamentos e paginação
        $transactions = Transaction::with(['client', 'gateway', 'products'])
            ->latest() // Ordena por created_at DESC
            ->paginate(10); // 10 registros por página

        return response()->json([
            'success' => true,
            'data' => $transactions->items(), // Dados da página atual
            'pagination' => [
                'total' => $transactions->total(),        // Total de registros
                'per_page' => $transactions->perPage(),   // Registros por página
                'current_page' => $transactions->currentPage(), // Página atual
                'last_page' => $transactions->lastPage(), // Última página
                'from' => $transactions->firstItem(),     // Primeiro item da página
                'to' => $transactions->lastItem(),        // Último item da página
            ]
        ]);
    }

    /**
     * Processa o reembolso de uma transação aprovada
     * 
     * Este endpoint inicia o processo de reembolso através do gateway
     * original utilizado na transação. Apenas transações com status
     * 'approved' podem ser reembolsadas.
     * 
     * Acesso: Apenas FINANCE e ADMIN (definido no middleware)
     * Fluxo: Validação → Reembolso no gateway → Atualização de status
     * 
     * @param Transaction $transaction
     * @return \Illuminate\Http\JsonResponse
     */
    public function refund(Transaction $transaction)
    {
        // Verifica se a transação está em estado reembolsável
        if ($transaction->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Apenas transações aprovadas podem ser reembolsadas'
            ], 400); // HTTP 400 - Bad Request
        }

        try {
            // Processa reembolso apenas se a transação foi processada por um gateway
            if ($transaction->gateway_id && $transaction->gateway) {
                $gateway = $transaction->gateway;

                // Chama o método específico baseado no gateway utilizado
                if ($gateway->name === 'Gateway 1') {
                    $this->paymentService->refundGateway1($transaction);
                } elseif ($gateway->name === 'Gateway 2') {
                    $this->paymentService->refundGateway2($transaction);
                }
                // Adicione novos gateways conforme necessário
            }

            // Atualiza o status da transação para refletir o reembolso
            $transaction->update([
                'status' => 'refunded'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transação reembolsada com sucesso',
                'data' => $transaction->fresh() // Recarrega dados atualizados
            ]);
        } catch (\Exception $e) {
            // Captura erros durante o processo de reembolso
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar reembolso',
                'error' => $e->getMessage() // Apenas para desenvolvimento
            ], 500); // HTTP 500 - Internal Server Error
        }
    }
}