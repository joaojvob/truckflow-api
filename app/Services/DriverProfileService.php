<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverProfileService
{
    public function __construct(
        protected DocumentStorageService $documentStorage,
    ) {}

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

    /**
     * Anexa ou substitui o arquivo da CNH do motorista.
     */
    public function uploadCnh(User $user, UploadedFile $file): DriverProfile
    {
        if (! $user->isDriver()) {
            throw ValidationException::withMessages([
                'file' => 'Apenas motoristas podem enviar CNH.',
            ]);
        }

        return DB::transaction(function () use ($user, $file) {
            $profile = DriverProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['tenant_id' => $user->tenant_id],
            );

            $path = $this->documentStorage->replace(
                $file,
                "driver-documents/{$user->tenant_id}/{$user->id}/cnh",
                $profile->cnh_file_path,
            );

            $profile->update([
                'cnh_file_path'   => $path,
                'cnh_uploaded_at' => now(),
            ]);

            return $profile->refresh();
        });
    }
}
