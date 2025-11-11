<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinanceMiddleware
{
    /**
     * Middleware de autorização para acesso de equipe financeira e administradores
     * 
     * Este middleware verifica se o usuário autenticado possui a role 'FINANCE' ou 'ADMIN'.
     * É aplicado em rotas relacionadas a operações financeiras como reembolsos e relatórios.
     * 
     * Hierarquia de acesso:
     * - ADMIN: Acesso completo (incluindo operações financeiras)
     * - FINANCE: Acesso específico a funcionalidades financeiras
     * - MANAGER/USER: Acesso negado
     * 
     * Fluxo:
     * 1. Verifica se existe um usuário autenticado
     * 2. Valida se o usuário possui role 'FINANCE' OU 'ADMIN'
     * 3. Permite acesso ou retorna erro 403
     * 
     * Casos de uso:
     * - Processamento de reembolsos de transações
     * - Acesso a relatórios financeiros
     * - Gestão de estornos e chargebacks
     * - Consulta de transações com dados financeiros completos
     * 
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obtém o usuário autenticado a partir do token Sanctum
        $user = $request->user();
        
        // Verificação em duas etapas com lógica OU para roles múltiplas:
        // 1. Se existe um usuário autenticado (não é null)
        // 2. Se o usuário possui role 'FINANCE' OU 'ADMIN'
        //    - $user->isFinance() verifica role === 'FINANCE'
        //    - $user->isAdmin() verifica role === 'ADMIN'
        if (!$user || (!$user->isFinance() && !$user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso não autorizado. Permissão de FINANCE ou ADMIN requerida.'
            ], 403); // HTTP 403 Forbidden - Acesso negado por falta de privilégios
        }

        // Usuário é FINANCE ou ADMIN - permite a continuidade da requisição
        return $next($request);
    }
}