<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Aplica isolamento multi-tenant automático em models.
 *
 * - Preenche `tenant_id` na criação com o tenant do usuário autenticado.
 * - Adiciona global scope filtrando queries pelo tenant atual.
 */
trait BelongsToTenant
{
    /**
     * Registra hooks de criação e escopo global de tenant.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            if (auth()->check() && empty($model->tenant_id)) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('tenant_id', auth()->user()->tenant_id);
            }
        });
    }
}
