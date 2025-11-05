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
        // TODO: Implementar autorização baseada em roles
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
        // Implementar lógica de reembolso
        // Por enquanto, apenas marcamos como refunded
        $transaction->update([
            'status' => 'refunded'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transação reembolsada com sucesso',
            'data' => $transaction
        ]);
    }
}
