<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function __construct(
        protected TenantService $tenantService,
    ) {}

    /**
     * Cria uma nova empresa e vincula o usuário autenticado como admin.
     * Só pode ser usado por usuários sem tenant.
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->tenant_id) {
            return response()->json([
                'message' => 'Você já está vinculado a uma empresa.',
            ], 422);
        }

        $tenant = $this->tenantService->create($request->validated(), $user);

        return response()->json([
            'data'    => TenantResource::make($tenant),
            'message' => 'Empresa criada com sucesso!',
        ], 201);
    }

    /**
     * Retorna os dados da empresa do usuário autenticado.
     */
    public function show(): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        if (! $tenant) {
            return response()->json([
                'message' => 'Você não está vinculado a nenhuma empresa.',
            ], 404);
        }

        return response()->json([
            'data' => TenantResource::make($tenant),
        ]);
    }

    /**
     * Atualiza os dados da empresa (somente admin).
     */
    public function update(UpdateTenantRequest $request): JsonResponse
    {
        $user   = auth()->user();
        $tenant = $user->tenant;

        if (! $tenant) {
            return response()->json([
                'message' => 'Você não está vinculado a nenhuma empresa.',
            ], 404);
        }

        if ($user->role->value !== 'admin') {
            return response()->json([
                'message' => 'Somente administradores podem atualizar a empresa.',
            ], 403);
        }

        $tenant = $this->tenantService->update($tenant, $request->validated());

        return response()->json([
            'data'    => TenantResource::make($tenant),
            'message' => 'Empresa atualizada com sucesso!',
        ]);
    }
}
