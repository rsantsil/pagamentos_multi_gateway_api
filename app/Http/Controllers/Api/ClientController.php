<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Lista todos os clientes do sistema com contagem de transações
     * 
     * Este endpoint retorna todos os clientes cadastrados, ordenados pelos mais recentes.
     * Inclui a contagem de transações para cada cliente, útil para dashboards e relatórios.
     * Acesso permitido para todos os usuários autenticados.
     * 
     * Eager Loading: withCount('transactions') - Conta as transações sem carregar os dados
     * Performance: latest()->get() - Ordena por criação (decrescente) e busca todos os registros
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Busca todos os clientes com contagem de transações relacionadas
        // withCount() é mais eficiente que carregar todas as transações quando só precisamos da contagem
        $clients = Client::withCount('transactions')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $clients
        ]);
    }

    /**
     * Exibe os detalhes de um cliente específico com seu histórico completo
     * 
     * Este endpoint retorna informações detalhadas de um cliente, incluindo:
     * - Dados básicos do cliente
     * - Todas as transações realizadas
     * - Gateway utilizado em cada transação
     * - Produtos adquiridos em cada transação
     * 
     * Utiliza Route Model Binding para automaticamente buscar o cliente pelo ID
     * Eager Loading com callback para otimizar as queries relacionadas
     * 
     * @param Client $client Instância do cliente via Route Model Binding
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Client $client)
    {
        // Carrega as relações com eager loading otimizado
        // transactions: Todas as transações do cliente
        // gateway: Dados do gateway utilizado em cada transação
        // products: Produtos adquiridos em cada transação (via pivot table)
        // latest(): Ordena transações pelas mais recentes primeiro
        $client->load(['transactions' => function($query) {
            $query->with(['gateway', 'products'])->latest();
        }]);

        return response()->json([
            'success' => true,
            'data' => $client
        ]);
    }
}