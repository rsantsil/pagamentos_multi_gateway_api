<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ManagerMiddleware
{
    /**
     * Middleware de autorização para acesso de gestores e administradores
     * 
     * Este middleware verifica se o usuário autenticado possui a role 'MANAGER' ou 'ADMIN'.
     * É aplicado em rotas relacionadas à gestão comercial e operacional do sistema.
     * 
     * Hierarquia de acesso:
     * - ADMIN: Acesso completo (incluindo gestão comercial)
     * - MANAGER: Acesso específico a funcionalidades de gestão de produtos e operações
     * - FINANCE/USER: Acesso negado
     * 
     * Fluxo:
     * 1. Verifica se existe um usuário autenticado
     * 2. Valida se o usuário possui role 'MANAGER' OU 'ADMIN'
     * 3. Permite acesso ou retorna erro 403
     * 
     * Casos de uso:
     * - Gestão do catálogo de produtos (CRUD completo)
     * - Configuração de preços e promoções
     * - Acesso a relatórios de vendas e performance
     * - Operações comerciais e gestão de ofertas
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
        // 2. Se o usuário possui role 'MANAGER' OU 'ADMIN'
        //    - $user->isManager() verifica role === 'MANAGER'
        //    - $user->isAdmin() verifica role === 'ADMIN'
        if (!$user || (!$user->isManager() && !$user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso não autorizado. Permissão de MANAGER ou ADMIN requerida.'
            ], 403); // HTTP 403 Forbidden - Acesso negado por falta de privilégios
        }

        // Usuário é MANAGER ou ADMIN - permite a continuidade da requisição
        return $next($request);
    }
}