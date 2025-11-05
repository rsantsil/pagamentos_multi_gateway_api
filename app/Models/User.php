<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // ✅ ADICIONAR ESTA LINHA

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // ✅ ADICIONAR HasApiTokens aqui

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isManager(): bool
    {
        return $this->role === 'MANAGER';
    }

    public function isFinance(): bool
    {
        return $this->role === 'FINANCE';
    }

    public function isUser(): bool
    {
        return $this->role === 'USER';
    }
}