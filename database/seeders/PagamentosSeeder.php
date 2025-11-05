<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PagamentosSeeder extends Seeder
{
    public function run(): void
    {
        // Gateways
        DB::table('gateways')->insert([
            ['name' => 'Gateway 1', 'is_active' => true, 'priority' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gateway 2', 'is_active' => true, 'priority' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Usuário admin
        DB::table('users')->insert([
            'name' => 'Administrador',
            'email' => 'admin@pagamentos.com',
            'password' => Hash::make('password'),
            'role' => 'ADMIN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Produtos
        DB::table('products')->insert([
            ['name' => 'Produto A', 'amount' => 10000, 'description' => 'Produto A - R$ 100,00', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Produto B', 'amount' => 5000, 'description' => 'Produto B - R$ 50,00', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Produto C', 'amount' => 2500, 'description' => 'Produto C - R$ 25,00', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}