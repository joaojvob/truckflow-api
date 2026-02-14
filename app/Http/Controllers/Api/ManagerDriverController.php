<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ManagerDriverController extends Controller
{
    /**
     * Lista os motoristas vinculados ao gestor autenticado.
     * GET /manager/drivers
     */
    public function index(): AnonymousResourceCollection
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isManager(), 403, 'Acesso negado.');

        $drivers = $user->drivers()->with('driverProfile')->paginate(15);

        return UserResource::collection($drivers);
    }

    /**
     * Vincula um motorista ao gestor autenticado.
     * POST /manager/drivers
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isManager(), 403, 'Acesso negado.');

        $validated = $request->validate([
            'driver_id' => ['required', 'exists:users,id'],
        ]);

        $driver = User::findOrFail($validated['driver_id']);

        abort_unless($driver->isDriver(), 422, 'O usuário informado não é um motorista.');

        // Evitar duplicata (sync sem remover os existentes)
        $user->drivers()->syncWithoutDetaching([$driver->id => [
            'tenant_id' => $user->tenant_id,
        ]]);

        return response()->json([
            'message' => "Motorista {$driver->name} vinculado com sucesso!",
        ], 201);
    }

    /**
     * Remove vínculo do motorista com o gestor autenticado.
     * DELETE /manager/drivers/{driver}
     */
    public function destroy(User $driver): JsonResponse
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isManager(), 403, 'Acesso negado.');

        $user->drivers()->detach($driver->id);

        return response()->json([
            'message' => "Motorista {$driver->name} desvinculado com sucesso!",
        ]);
    }
}
