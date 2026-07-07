<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisionamento e manutenção de tenants (empresas) no modelo multi-tenant.
 */
class TenantService
{
    /**
     * Cria uma empresa e vincula o usuário como administrador.
     *
     * @param  array{name: string, slug?: string, settings?: array<string, mixed>}  $data  Dados da empresa.
     * @param  User  $owner  Usuário que se tornará admin do tenant.
     * @return Tenant Empresa criada.
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
     * Atualiza nome, slug ou configurações da empresa.
     *
     * @param  Tenant  $tenant  Empresa a editar.
     * @param  array<string, mixed>  $data  Campos validados.
     * @return Tenant Empresa atualizada.
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant->fresh();
    }

    /**
     * Atualiza configurações fiscais do tenant (CNPJ, IE, razão social).
     *
     * @param  array{cnpj: string, ie: string, razao_social: string, uf: string, municipio: string}  $fiscal
     */
    public function updateFiscalSettings(Tenant $tenant, array $fiscal): Tenant
    {
        $settings = $tenant->settings ?? [];
        $settings['fiscal'] = $fiscal;

        $tenant->update(['settings' => $settings]);

        return $tenant->fresh();
    }
}
