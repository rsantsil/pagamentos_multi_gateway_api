<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Lista todos os usuários do sistema
     * 
     * Este endpoint retorna todos os usuários cadastrados no sistema.
     * Inclui informações básicas como nome, email e role de cada usuário.
     * 
     * Acesso: Apenas ADMIN (definido no middleware das rotas)
     * Segurança: Não retorna senhas ou tokens de acesso
     * Uso: Gestão de usuários e administração do sistema
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $users = User::all();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Cria um novo usuário no sistema
     * 
     * Este endpoint permite a criação de novos usuários com roles específicas.
     * A senha é automaticamente hasheada antes do armazenamento.
     * 
     * Acesso: Apenas ADMIN (definido no middleware das rotas)
     * Validação: Email único e role válida
     * Segurança: Hash automático da senha com bcrypt
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validação rigorosa dos dados de entrada
        $request->validate([
            'name' => 'required|string|max:255',                            // Nome obrigatório
            'email' => 'required|email|unique:users',                       // Email único no sistema
            'password' => 'required|string|min:6',                          // Senha mínima 6 caracteres
            'role' => ['required', Rule::in(['ADMIN', 'MANAGER', 'FINANCE', 'USER'])] // Role válida
        ]);

        // Criação do usuário com senha hasheada
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), // Hash com bcrypt
            'role' => $request->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuário criado com sucesso',
            'data' => $user
        ], 201); // HTTP 201 - Created
    }

    /**
     * Exibe os detalhes de um usuário específico
     * 
     * Este endpoint retorna informações completas de um usuário.
     * Utiliza Route Model Binding para busca automática.
     * 
     * Acesso: Apenas ADMIN (definido no middleware das rotas)
     * Segurança: Não expõe dados sensíveis como senha
     * 
     * @param User $user Instância do usuário via Route Model Binding
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Atualiza os dados de um usuário existente
     * 
     * Este endpoint permite editar informações de um usuário.
     * Suporta atualizações parciais e valida email único com ignore.
     * 
     * Acesso: Apenas ADMIN (definido no middleware das rotas)
     * Validação: 'sometimes' permite atualizações parciais
     * Segurança: Hash automático se senha for alterada
     * 
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, User $user)
    {
        // Validação com 'sometimes' - regras aplicadas apenas se campo presente
        $request->validate([
            'name' => 'sometimes|string|max:255',                           // Nome opcional
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)], // Email único exceto próprio
            'password' => 'sometimes|string|min:6',                         // Nova senha opcional
            'role' => ['sometimes', Rule::in(['ADMIN', 'MANAGER', 'FINANCE', 'USER'])] // Role opcional
        ]);

        // Prepara dados para atualização
        $data = $request->only(['name', 'email', 'role']);
        
        // Aplica hash apenas se uma nova senha foi fornecida
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Usuário atualizado com sucesso',
            'data' => $user
        ]);
    }

    /**
     * Remove um usuário do sistema
     * 
     * Este endpoint exclui permanentemente um usuário.
     * IMPEDE que um usuário delete sua própria conta por questões de segurança.
     * 
     * Acesso: Apenas ADMIN (definido no middleware das rotas)
     * Segurança: Verificação de auto-exclusão
     * Consideração: Em produção, considere soft delete
     * 
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, User $user)
    {
        // Impede que o usuário autenticado delete sua própria conta
        // Medida de segurança para evitar lockout acidental
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Você não pode deletar sua própria conta'
            ], 403); // HTTP 403 - Forbidden
        }

        // Exclusão permanente do usuário
        // Nota: Transações históricas podem manter referência, mas o usuário
        // não poderá mais acessar o sistema
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuário deletado com sucesso'
        ]);
    }
}