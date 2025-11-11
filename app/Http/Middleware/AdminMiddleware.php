<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Middleware de autorização para acesso exclusivo de administradores
     * 
     * Este middleware verifica se o usuário autenticado possui a role 'ADMIN'.
     * É aplicado em rotas que requerem privilégios administrativos completos.
     * 
     * Fluxo:
     * 1. Verifica se existe um usuário autenticado
     * 2. Valida se o usuário possui a role 'ADMIN'
     * 3. Permite acesso ou retorna erro 403
     * 
     * Casos de uso:
     * - Gestão de usuários (CRUD completo)
     * - Configuração de gateways de pagamento
     * - Acesso a relatórios administrativos
     * - Configurações globais do sistema
     * 
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obtém o usuário autenticado a partir do token Sanctum
        $user = $request->user();
        
        // Verifica em duas etapas para segurança:
        // 1. Se existe um usuário autenticado (não é null)
        // 2. Se o usuário possui a role 'ADMIN' usando o método isAdmin() do modelo User
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso não autorizado. Permissão de ADMIN requerida.'
            ], 403); // HTTP 403 Forbidden - Acesso negado por falta de privilégios
        }

        // Usuário é ADMIN - permite a continuidade da requisição
        return $next($request);
    }
}