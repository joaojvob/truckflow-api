<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DriverProfileService
{
    /**
     * Cria ou atualiza o perfil do motorista.
     */
    public function createOrUpdate(User $user, array $data): DriverProfile
    {
        return DB::transaction(function () use ($user, $data) {
            $profile = DriverProfile::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($data, ['tenant_id' => $user->tenant_id]),
            );

            return $profile->refresh();
        });
    }
}
