<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gerencia o perfil profissional do motorista e upload da CNH.
 */
class DriverProfileService
{
    public function __construct(
        protected DocumentStorageService $documentStorage,
    ) {}

    /**
     * Cria ou atualiza os dados cadastrais do motorista.
     *
     * @param  User  $user  Motorista autenticado.
     * @param  array<string, mixed>  $data  Campos validados (CNH, endereço, etc.).
     * @return DriverProfile Perfil persistido.
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
     * Anexa ou substitui o arquivo digital da CNH no storage privado.
     *
     * @param  User  $user  Motorista autenticado.
     * @param  UploadedFile  $file  Imagem ou PDF da CNH.
     * @return DriverProfile Perfil com `cnh_file_path` e `cnh_uploaded_at` atualizados.
     *
     * @throws ValidationException Se o usuário não for motorista.
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
