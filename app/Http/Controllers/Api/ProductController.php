<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Lista todos os produtos ativos do catálogo
     * 
     * Este endpoint retorna apenas produtos com status ativo (is_active = true),
     * garantindo que produtos desativados não sejam exibidos para compra.
     * 
     * Acesso: Público (não requer autenticação)
     * Uso: Catálogo de produtos disponíveis para compra
     * Filtro: where('is_active', true) - Exclui produtos desativados
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Filtra apenas produtos ativos para exibição no catálogo
        // Produtos desativados são mantidos no banco para histórico de transações
        $products = Product::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Exibe os detalhes de um produto específico
     * 
     * Este endpoint retorna informações completas de um produto,
     * independente do seu status (ativo ou inativo).
     * 
     * Acesso: Público (não requer autenticação)
     * Uso: Página de detalhes do produto
     * Route Model Binding: Busca automática pelo ID
     * 
     * @param Product $product Instância do produto via Route Model Binding
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Cria um novo produto no catálogo
     * 
     * Este endpoint permite a criação de novos produtos no sistema.
     * Novos produtos são criados automaticamente como ativos.
     * 
     * Acesso: Apenas MANAGER e ADMIN (definido no middleware das rotas)
     * Validação: Campos obrigatórios e formatos específicos
     * Status: Produtos são criados como ativos por padrão
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validação dos dados de entrada
        $request->validate([
            'name' => 'required|string|max:255',        // Nome obrigatório com limite
            'amount' => 'required|integer|min:1',       // Valor em centavos, mínimo 1
            'description' => 'nullable|string',         // Descrição opcional
        ]);

        // Criação do produto com valores padrão
        $product = Product::create([
            'name' => $request->name,
            'amount' => $request->amount,               // Armazenado em centavos (ex: 10000 = R$ 100,00)
            'description' => $request->description,
            'is_active' => true,                        // Ativo por padrão
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produto criado com sucesso',
            'data' => $product
        ], 201); // HTTP 201 - Created
    }

    /**
     * Atualiza um produto existente
     * 
     * Este endpoint permite editar informações de um produto.
     * Utiliza 'sometimes' nas validações para permitir atualizações parciais.
     * 
     * Acesso: Apenas MANAGER e ADMIN (definido no middleware das rotas)
     * Validação: Campos opcionais com regras quando presentes
     * Atualização: $request->all() atualiza apenas os campos enviados
     * 
     * @param Request $request
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Product $product)
    {
        // Validação com 'sometimes' - aplica regras apenas se o campo estiver presente
        $request->validate([
            'name' => 'sometimes|string|max:255',       // Opcional, mas se enviado deve ser string
            'amount' => 'sometimes|integer|min:1',      // Opcional, mas se enviado deve ser ≥ 1
            'description' => 'nullable|string',         // Sempre opcional, pode ser null
            'is_active' => 'sometimes|boolean',         // Permite ativar/desativar produto
        ]);

        // Atualização parcial - apenas campos enviados são modificados
        $product->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Produto atualizado com sucesso',
            'data' => $product
        ]);
    }

    /**
     * Remove um produto do sistema
     * 
     * Este endpoint exclui permanentemente um produto.
     * CUIDADO: Esta operação é irreversível e pode afetar
     * o histórico de transações que referenciam este produto.
     * 
     * Acesso: Apenas MANAGER e ADMIN (definido no middleware das rotas)
     * Consideração: Em produção, considere soft delete ou arquivamento
     * 
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Product $product)
    {
        // Exclusão permanente do produto
        // Nota: Transações históricas manterão referência ao produto excluído
        // mas não poderão carregar seus dados via relacionamento
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produto deletado com sucesso'
        ]);
    }
}