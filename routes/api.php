<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\GatewayController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ClientController;
use Illuminate\Support\Facades\Route;

/**
 * ROTAS DA API - SISTEMA DE PAGAMENTOS MULTI-GATEWAY
 * 
 * Este arquivo define todas as rotas da API, organizadas por nível de acesso
 * e agrupadas por funcionalidade. A hierarquia de acesso é implementada
 * através de middlewares que verificam a role do usuário autenticado.
 * 
 * Hierarquia de Roles:
 * - USER: Operações básicas (consulta, compras)
 * - MANAGER: Gestão de produtos e operações comerciais  
 * - FINANCE: Processamento de reembolsos e operações financeiras
 * - ADMIN: Acesso completo ao sistema
 * 
 * Estrutura dos grupos:
 * 1. Rotas Públicas - Acesso sem autenticação
 * 2. Rotas Protegidas - Requer autenticação (qualquer role)
 * 3. Rotas por Role - Acesso restrito por hierarquia
 */

// =============================================================================
// ROTAS PÚBLICAS - ACESSO SEM AUTENTICAÇÃO
// =============================================================================

/**
 * Autenticação e operações essenciais disponíveis para qualquer usuário
 * Estas rotas não requerem token de autenticação
 */
Route::post('/login', [AuthController::class, 'login']);                      // Autenticação de usuários
Route::post('/purchase', [TransactionController::class, 'purchase']);         // Processamento de compras
Route::get('/products', [ProductController::class, 'index']);                 // Listagem de produtos ativos
Route::get('/products/{product}', [ProductController::class, 'show']);        // Detalhes de produto específico


// =============================================================================
// ROTAS PROTEGIDAS - ACESSO COM AUTENTICAÇÃO (QUALQUER ROLE)
// =============================================================================

/**
 * Rotas que requerem autenticação mas estão disponíveis para todas as roles
 * O middleware 'auth:sanctum' garante que o usuário possui um token válido
 */
Route::middleware('auth:sanctum')->group(function () {
    // Operações de autenticação e perfil
    Route::post('/logout', [AuthController::class, 'logout']);                // Encerrar sessão
    Route::get('/user', [AuthController::class, 'user']);                     // Dados do usuário logado
    
    // Consulta de gateways ativos
    Route::get('/gateways', [GatewayController::class, 'index']);             // Listar gateways (ordenados por prioridade)
    
    // Histórico e consulta de transações
    Route::get('/transactions', [TransactionController::class, 'index']);     // Listar transações (paginação)
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']); // Detalhes de transação
    
    // Consulta de clientes
    Route::get('/clients', [ClientController::class, 'index']);               // Listar todos os clientes
    Route::get('/clients/{client}', [ClientController::class, 'show']);       // Detalhes de cliente específico
});


// =============================================================================
// ROTAS RESTRITAS - ACESSO APENAS PARA ADMIN
// =============================================================================

/**
 * Rotas de administração do sistema - acesso exclusivo para role ADMIN
 * Operações críticas que afetam todo o sistema
 */
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Gestão de gateways de pagamento
    Route::patch('/gateways/{gateway}/toggle', [GatewayController::class, 'toggle']);         // Ativar/desativar gateway
    Route::patch('/gateways/{gateway}/priority', [GatewayController::class, 'updatePriority']); // Alterar prioridade
    
    // Gestão completa de usuários
    Route::apiResource('users', UserController::class);                       // CRUD completo de usuários
    // GET /users           - Listar todos os usuários
    // POST /users          - Criar novo usuário  
    // GET /users/{user}    - Ver usuário específico
    // PUT /users/{user}    - Atualizar usuário
    // DELETE /users/{user} - Excluir usuário
});


// =============================================================================
// ROTAS RESTRITAS - ACESSO PARA MANAGER E ADMIN
// =============================================================================

/**
 * Rotas de gestão comercial - acesso para MANAGER e ADMIN
 * Operações relacionadas ao catálogo de produtos e operações comerciais
 */
Route::middleware(['auth:sanctum', 'manager'])->group(function () {
    // Gestão do catálogo de produtos
    Route::post('/products', [ProductController::class, 'store']);            // Criar novo produto
    Route::put('/products/{product}', [ProductController::class, 'update']);  // Atualizar produto existente
    Route::delete('/products/{product}', [ProductController::class, 'destroy']); // Excluir produto
});


// =============================================================================
// ROTAS RESTRITAS - ACESSO PARA FINANCE E ADMIN  
// =============================================================================

/**
 * Rotas financeiras - acesso para FINANCE e ADMIN
 * Operações monetárias críticas como reembolsos e estornos
 */
Route::middleware(['auth:sanctum', 'finance'])->group(function () {
    // Processamento de reembolsos
    Route::post('/transactions/{transaction}/refund', [TransactionController::class, 'refund']); // Reembolsar transação
});


// =============================================================================
// RESUMO DA HIERARQUIA DE ACESSO POR ROLE
// =============================================================================

/**
 * USER (Operações Básicas):
 * ✅ Login, Logout, Perfil
 * ✅ Listar produtos, Comprar
 * ✅ Listar transações próprias, Consultar clientes
 * ✅ Listar gateways ativos
 * 
 * MANAGER (Gestão Comercial):
 * ✅ Todas as permissões de USER
 * ✅ Criar, editar e excluir produtos
 * 
 * FINANCE (Operações Financeiras):
 * ✅ Todas as permissões de USER  
 * ✅ Processar reembolsos de transações
 * 
 * ADMIN (Acesso Completo):
 * ✅ Todas as permissões anteriores
 * ✅ Gerenciar usuários (CRUD completo)
 * ✅ Configurar gateways (ativação, prioridade)
 */

// =============================================================================
// MIDDLEWERS UTILIZADOS
// =============================================================================

/**
 * auth:sanctum    - Verifica autenticação via token Sanctum
 * admin           - Apenas role ADMIN
 * manager         - Apenas roles MANAGER e ADMIN  
 * finance         - Apenas roles FINANCE e ADMIN
 */