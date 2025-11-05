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
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Transações
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('/transactions/{transaction}/refund', [TransactionController::class, 'refund']);
    
    // Gateways
    Route::get('/gateways', [GatewayController::class, 'index']);
    Route::patch('/gateways/{gateway}/toggle', [GatewayController::class, 'toggle']);
    Route::patch('/gateways/{gateway}/priority', [GatewayController::class, 'updatePriority']);
    
    // Produtos
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);
    
    // Usuários
    Route::apiResource('users', UserController::class);
    
    // Clientes
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
});