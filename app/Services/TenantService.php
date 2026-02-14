<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantService
{
    /**
     * Cria uma nova empresa (tenant) e vincula o usuário como admin.
     */
    public function create(array $data, User $owner): Tenant
    {
        return DB::transaction(function () use ($data, $owner) {
            $tenant = Tenant::create([
                'name'     => $data['name'],
                'slug'     => $data['slug'] ?? Str::slug($data['name']),
                'settings' => $data['settings'] ?? [],
            ]);

            $owner->update([
                'tenant_id' => $tenant->id,
                'role'      => 'admin',
            ]);

            return $tenant->fresh();
        });
    }

    /**
     * Atualiza os dados da empresa.
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant->fresh();
    }
}
