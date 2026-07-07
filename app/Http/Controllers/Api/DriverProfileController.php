<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDriverProfileRequest;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DriverProfileResource;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\DocumentStorageService;
use App\Services\DriverProfileService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverProfileController extends Controller
{
    public function __construct(
        protected DriverProfileService $profileService,
        protected DocumentStorageService $documentStorage,
    ) {}

    /**
     * Retorna o perfil do motorista autenticado.
     */
    public function show(): JsonResponse
    {
        $profile = auth()->user()->driverProfile;

        if (! $profile) {
            return response()->json([
                'data'    => null,
                'message' => 'Perfil de motorista ainda não cadastrado.',
            ]);
        }

        return response()->json([
            'data' => DriverProfileResource::make($profile),
        ]);
    }

    /**
     * Cria ou atualiza o perfil do motorista autenticado.
     */
    public function update(UpdateDriverProfileRequest $request): JsonResponse
    {
        $profile = $this->profileService->createOrUpdate(
            auth()->user(),
            $request->validated(),
        );

        $isNew = $profile->wasRecentlyCreated;

        return response()->json([
            'data'    => DriverProfileResource::make($profile),
            'message' => $isNew
                ? 'Perfil de motorista criado com sucesso!'
                : 'Perfil de motorista atualizado com sucesso!',
        ], $isNew ? 201 : 200);
    }

    /**
     * Motorista envia arquivo da CNH.
     * POST /driver-profile/cnh
     */
    public function uploadCnh(UploadDocumentRequest $request): JsonResponse
    {
        $this->authorize('uploadCnh', DriverProfile::class);

        $profile = $this->profileService->uploadCnh(
            auth()->user(),
            $request->file('file'),
        );

        return response()->json([
            'data'    => DriverProfileResource::make($profile),
            'message' => 'CNH enviada com sucesso!',
        ], 201);
    }

    /**
     * Download da CNH (motorista ou gestor/admin do tenant).
     * GET /driver-profile/cnh
     */
    public function downloadCnh(): StreamedResponse
    {
        $profile = auth()->user()->driverProfile;

        abort_unless($profile, 404, 'Perfil de motorista não encontrado.');

        $this->authorize('viewCnh', $profile);

        return $this->documentStorage->download(
            $profile->cnh_file_path,
            'cnh-'.auth()->id().'.'.pathinfo($profile->cnh_file_path, PATHINFO_EXTENSION),
        );
    }

    /**
     * Gestor/admin baixa CNH de um motorista do tenant.
     * GET /users/{user}/cnh
     */
    public function downloadCnhForUser(User $user): StreamedResponse
    {
        abort_unless($user->isDriver(), 404, 'Motorista não encontrado.');

        $profile = $user->driverProfile;
        abort_unless($profile, 404, 'Perfil de motorista não encontrado.');

        $this->authorize('viewCnh', $profile);

        return $this->documentStorage->download(
            $profile->cnh_file_path,
            'cnh-'.$user->id.'.'.pathinfo($profile->cnh_file_path, PATHINFO_EXTENSION),
        );
    }
}
