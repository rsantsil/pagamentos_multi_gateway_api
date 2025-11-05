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

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['client', 'gateway', 'products']);

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }

    public function purchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'card_number' => 'required|string|size:16',
            'cvv' => 'required|string|min:3|max:4',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Calcular valor total
        $totalAmount = 0;
        $productsData = [];

        foreach ($request->products as $item) {
            $product = \App\Models\Product::find($item['id']);
            $totalAmount += $product->amount * $item['quantity'];
            $productsData[] = [
                'id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_amount' => $product->amount,
            ];
        }

        $paymentData = [
            'amount' => $totalAmount,
            'card_number' => $request->card_number,
            'cvv' => $request->cvv,
            'client_name' => $request->client_name,
            'client_email' => $request->client_email,
        ];

        $result = $this->paymentService->processPayment($paymentData, $productsData);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function index(Request $request)
    {
        $transactions = Transaction::with(['client', 'gateway', 'products'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ]
        ]);
    }

    public function refund(Transaction $transaction)
    {
        // Verificar se a transação pode ser reembolsada
        if ($transaction->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Apenas transações aprovadas podem ser reembolsadas'
            ], 400);
        }

        try {
            // Chamar o gateway para reembolso real apenas se tiver gateway_id
            if ($transaction->gateway_id && $transaction->gateway) {
                $gateway = $transaction->gateway;

                if ($gateway->name === 'Gateway 1') {
                    $this->paymentService->refundGateway1($transaction);
                } elseif ($gateway->name === 'Gateway 2') {
                    $this->paymentService->refundGateway2($transaction);
                }
            }

            // Atualizar status da transação
            $transaction->update([
                'status' => 'refunded'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transação reembolsada com sucesso',
                'data' => $transaction->fresh()
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar reembolso',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
