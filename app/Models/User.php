<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, BelongsToTenant, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function freights(): HasMany
    {
        return $this->hasMany(Freight::class, 'driver_id');
    }

    public function managedFreights(): HasMany
    {
        return $this->hasMany(Freight::class, 'created_by');
    }

    public function driverProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DriverProfile::class);
    }

    public function trucks(): HasMany
    {
        return $this->hasMany(Truck::class, 'driver_id');
    }

    public function trailers(): HasMany
    {
        return $this->hasMany(Trailer::class, 'driver_id');
    }

    /**
     * Motoristas vinculados a este gestor.
     */
    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'manager_driver', 'manager_id', 'driver_id')
            ->withTimestamps();
    }

    /**
     * Gestores vinculados a este motorista.
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'manager_driver', 'driver_id', 'manager_id')
            ->withTimestamps();
    }

    public function isDriver(): bool
    {
        return $this->role === UserRole::Driver;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }
}