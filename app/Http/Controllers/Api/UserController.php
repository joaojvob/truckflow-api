<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Lista os usuários da empresa (admin/manager).
     */
    public function index(): AnonymousResourceCollection
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isManager(), 403, 'Acesso restrito.');

        $users = User::with(['driverProfile'])
            ->latest()
            ->paginate(15);

        return UserResource::collection($users);
    }

    /**
     * Exibe um usuário específico da empresa.
     */
    public function show(User $user): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isManager(), 403, 'Acesso restrito.');

        $user->load(['driverProfile', 'trucks', 'trailers']);

        return response()->json([
            'data' => UserResource::make($user),
        ]);
    }

    /**
     * Atualiza role de um usuário (somente admin).
     */
    public function updateRole(User $user): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Acesso restrito a administradores.');
        $validated = request()->validate([
            'role' => ['required', 'string', 'in:admin,manager,driver'],
        ]);

        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Você não pode alterar seu próprio cargo.',
            ], 422);
        }

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'data'    => UserResource::make($user->fresh()),
            'message' => 'Cargo do usuário atualizado com sucesso!',
        ]);
    }
}
