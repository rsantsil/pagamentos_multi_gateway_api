<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use Illuminate\Http\Request;

class GatewayController extends Controller
{
    public function index()
    {
        $gateways = Gateway::orderBy('priority')->get();

        return response()->json([
            'success' => true,
            'data' => $gateways
        ]);
    }

    public function toggle(Gateway $gateway)
    {
        $gateway->update([
            'is_active' => !$gateway->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gateway ' . ($gateway->is_active ? 'ativado' : 'desativado') . ' com sucesso',
            'data' => $gateway
        ]);
    }

    public function updatePriority(Gateway $gateway, Request $request)
    {
        $request->validate([
            'priority' => 'required|integer|min:1'
        ]);

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