<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Processa o login do usuário e retorna token de autenticação
     * 
     * Este endpoint é público e permite que usuários se autentiquem no sistema.
     * Após validação bem-sucedida das credenciais, um token Sanctum é gerado
     * para autenticação em requisições subsequentes.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            // Validação dos campos obrigatórios
            // email: deve ser um email válido e obrigatório
            // password: campo obrigatório
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            // Busca o usuário no banco de dados pelo email
            // Utiliza first() para retornar apenas o primeiro registro encontrado
            $user = User::where('email', $request->email)->first();

            // Verifica se o usuário existe
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado.',
                ], 404); // HTTP 404 - Not Found
            }

            // Verifica se a senha fornecida corresponde à senha hash no banco
            // Hash::check compara a senha em texto plano com o hash armazenado
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Senha incorreta.',
                ], 401); // HTTP 401 - Unauthorized
            }

            // Cria um novo token de autenticação usando Laravel Sanctum
            // O token é retornado como string plain text para uso no header Authorization
            $token = $user->createToken('auth-token')->plainTextToken;

            // Retorna resposta de sucesso com token e dados do usuário
            // O campo 'user' inclui informações básicas sem dados sensíveis
            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role, // Role é importante para autorização nas rotas
                ],
            ]);

        } catch (\Exception $e) {
            // Captura qualquer exceção não tratada e retorna erro genérico
            // Em produção, considere logar o erro para debugging
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor.',
                'error' => $e->getMessage() // Apenas para desenvolvimento
            ], 500); // HTTP 500 - Internal Server Error
        }
    }

    /**
     * Realiza logout do usuário revogando o token atual
     * 
     * Este endpoint requer autenticação via token Sanctum.
     * Remove o token de acesso atual do usuário, invalidando-o
     * para requisições futuras.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            // Revoga o token de acesso atual do usuário autenticado
            // currentAccessToken() retorna a instância do token em uso
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout realizado com sucesso.',
            ]);
        } catch (\Exception $e) {
            // Em caso de erro na revogação do token
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer logout.',
            ], 500); // HTTP 500 - Internal Server Error
        }
    }

    /**
     * Retorna os dados do usuário atualmente autenticado
     * 
     * Endpoint útil para verificar a autenticação e obter
     * informações do usuário logado. Requer token válido.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        try {
            // Retorna os dados completos do usuário autenticado
            // $request->user() retorna a instância do modelo User via Sanctum
            return response()->json([
                'success' => true,
                'user' => $request->user(), // Inclui todos os campos do modelo User
            ]);
        } catch (\Exception $e) {
            // Erro ao acessar dados do usuário (possivelmente token inválido)
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter dados do usuário.',
            ], 500); // HTTP 500 - Internal Server Error
        }
    }
}