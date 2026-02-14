<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDriverProfileRequest;
use App\Http\Resources\DriverProfileResource;
use App\Services\DriverProfileService;
use Illuminate\Http\JsonResponse;

class DriverProfileController extends Controller
{
    public function __construct(
        protected DriverProfileService $profileService,
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
}
