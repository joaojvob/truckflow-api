<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, BelongsToTenant, HasApiTokens; 

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id', 
        'role',      
    ];

    /**
     * Atributos que devem ser ocultados na serialização (API).
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts de tipos.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relacionamento: O usuário pertence a uma Transportadora (Tenant).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relacionamento: Se o usuário for um motorista, ele possui vários fretes.
     */
    public function freights(): HasMany
    {
        return $this->hasMany(Freight::class, 'driver_id');
    }

    /**
     * Helper para verificar permissões de forma limpa.
     */
    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}