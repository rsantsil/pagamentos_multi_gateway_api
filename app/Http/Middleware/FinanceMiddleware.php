<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinanceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user || (!$user->isFinance() && !$user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso não autorizado. Permissão de FINANCE ou ADMIN requerida.'
            ], 403);
        }

        return $next($request);
    }
}