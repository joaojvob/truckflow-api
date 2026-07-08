<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManagerDriverRequest;
use App\Http\Resources\UserResource;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ManagerDriverController extends Controller
{
    /**
     * Lista os motoristas vinculados ao gestor autenticado.
     * GET /manager/drivers
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isManager(), 403, 'Acesso negado.');

        $query = $user->drivers()->with('driverProfile');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($inner) use ($search) {
                $inner->where('users.name', 'ilike', "%{$search}%")
                    ->orWhere('users.email', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('available')) {
            $available = filter_var($request->input('available'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('driverProfile', fn ($q) => $q->where('is_available', $available));
        }

        $drivers = $query->paginate(15)->withQueryString();

        return UserResource::collection($drivers);
    }

    /**
     * Cadastra um novo motorista e já o vincula ao gestor autenticado.
     * POST /manager/drivers/register
     */
    public function register(StoreManagerDriverRequest $request): JsonResponse
    {
        $manager = auth()->user();
        $validated = $request->validated();

        $driver = DB::transaction(function () use ($manager, $validated) {
            $driver = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => $validated['password'],
                'role'      => UserRole::Driver,
                'tenant_id' => $manager->tenant_id,
            ]);

            DriverProfile::create([
                'user_id'      => $driver->id,
                'tenant_id'    => $manager->tenant_id,
                'phone'        => $validated['phone'] ?? null,
                'cpf'          => $validated['cpf'] ?? null,
                'cnh_number'   => $validated['cnh_number'] ?? null,
                'cnh_category' => $validated['cnh_category'] ?? null,
                'cnh_expiry'   => $validated['cnh_expiry'] ?? null,
                'is_available' => true,
            ]);

            $manager->drivers()->syncWithoutDetaching([$driver->id => [
                'tenant_id' => $manager->tenant_id,
            ]]);

            return $driver;
        });

        return response()->json([
            'data'    => UserResource::make($driver->load('driverProfile')),
            'message' => "Motorista {$driver->name} cadastrado e vinculado com sucesso!",
        ], 201);
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
