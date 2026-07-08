<?php

namespace App\Traits;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aplica isolamento multi-tenant automático em models.
 *
 * - Preenche `tenant_id` na criação com o tenant do contexto atual.
 * - Adiciona global scope filtrando queries pelo tenant do contexto.
 *
 * O tenant do contexto é resolvido pelo middleware ResolveTenantContext:
 * usuário comum = seu tenant; super admin = tenant do header (ou nenhum, vendo tudo).
 */
trait BelongsToTenant
{
    /**
     * Registra hooks de criação e escopo global de tenant.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $contextTenantId = app(TenantContext::class)->id();

                if ($contextTenantId) {
                    $model->tenant_id = $contextTenantId;
                } elseif (auth()->check() && ! auth()->user()->isSuperAdmin()) {
                    $model->tenant_id = auth()->user()->tenant_id;
                }
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            if (! auth()->check()) {
                return;
            }

            $column = $builder->getModel()->qualifyColumn('tenant_id');
            $context = app(TenantContext::class);

            if (auth()->user()->isSuperAdmin()) {
                // Super admin com empresa selecionada filtra por ela; sem seleção, vê tudo.
                if ($context->has()) {
                    $builder->where($column, $context->id());
                }

                return;
            }

            $builder->where($column, $context->has() ? $context->id() : auth()->user()->tenant_id);
        });
    }
}
