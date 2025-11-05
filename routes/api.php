<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\GatewayController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ClientController;
use Illuminate\Support\Facades\Route;

// Rotas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/purchase', [TransactionController::class, 'purchase']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// Rotas protegidas
Route::middleware('auth:sanctum')->group(function () {
    // Auth (acesso a todos usuários autenticados)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Gateways - todos autenticados podem listar
    Route::get('/gateways', [GatewayController::class, 'index']);
    
    // Transações - todos os autenticados
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    
    // Clientes - todos os autenticados
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
});

// Rotas apenas para ADMIN
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Gestão de gateways (apenas ADMIN)
    Route::patch('/gateways/{gateway}/toggle', [GatewayController::class, 'toggle']);
    Route::patch('/gateways/{gateway}/priority', [GatewayController::class, 'updatePriority']);
    
    // Gestão de usuários (apenas ADMIN)
    Route::apiResource('users', UserController::class);
});

// Rotas para MANAGER e ADMIN (gestão de produtos)
Route::middleware(['auth:sanctum', 'manager'])->group(function () {
    // Gestão de produtos (MANAGER e ADMIN)
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
});

// Rotas para FINANCE e ADMIN (apenas reembolsos)
Route::middleware(['auth:sanctum', 'finance'])->group(function () {
    // Reembolsos (FINANCE e ADMIN)
    Route::post('/transactions/{transaction}/refund', [TransactionController::class, 'refund']);
});