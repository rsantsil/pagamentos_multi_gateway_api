<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use Illuminate\Http\Request;

class GatewayController extends Controller
{
    /**
     * Lista todos os gateways de pagamento ordenados por prioridade
     * 
     * Este endpoint retorna todos os gateways configurados no sistema,
     * ordenados pela prioridade definida (menor número = maior prioridade).
     * 
     * Acesso: Todos os usuários autenticados podem listar gateways
     * Uso: Utilizado pelo frontend para mostrar opções disponíveis e pelo
     * PaymentService para determinar a ordem de tentativa de processamento
     * 
     * Ordenação: orderBy('priority') - Gateways com priority=1 são tentados primeiro
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Busca todos os gateways ordenados por prioridade (ascendente)
        // Prioridade 1 = primeiro na fila de processamento
        $gateways = Gateway::orderBy('priority')->get();

        return response()->json([
            'success' => true,
            'data' => $gateways
        ]);
    }

    /**
     * Ativa ou desativa um gateway de pagamento
     * 
     * Este endpoint permite alternar o status de atividade de um gateway.
     * Gateways desativados são ignorados pelo PaymentService durante o
     * processamento de pagamentos, mesmo que tenham alta prioridade.
     * 
     * Acesso: Apenas usuários com role ADMIN
     * Uso: Permitir manutenção sem remover a configuração do gateway
     * 
     * @param Gateway $gateway Instância do gateway via Route Model Binding
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggle(Gateway $gateway)
    {
        // Alterna o status de atividade do gateway
        // Se estava ativo (true) → desativa (false)
        // Se estava inativo (false) → ativa (true)
        $gateway->update([
            'is_active' => !$gateway->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gateway ' . ($gateway->is_active ? 'ativado' : 'desativado') . ' com sucesso',
            'data' => $gateway
        ]);
    }

    /**
     * Atualiza a prioridade de processamento de um gateway
     * 
     * Este endpoint permite modificar a ordem de tentativa dos gateways.
     * Gateways com menor número de prioridade são tentados primeiro.
     * É recomendável manter prioridades únicas para comportamento previsível.
     * 
     * Acesso: Apenas usuários com role ADMIN
     * Validação: Priority deve ser inteiro positivo (min: 1)
     * 
     * @param Gateway $gateway Instância do gateway via Route Model Binding
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePriority(Gateway $gateway, Request $request)
    {
        // Valida se a prioridade é um inteiro positivo
        $request->validate([
            'priority' => 'required|integer|min:1' // Prioridade mínima é 1
        ]);

        // Atualiza a prioridade do gateway
        // Importante: Verificar conflitos de prioridade pode ser uma melhoria futura
        $gateway->update([
            'priority' => $request->priority
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prioridade do gateway atualizada com sucesso',
            'data' => $gateway
        ]);
    }
}