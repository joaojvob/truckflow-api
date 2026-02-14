<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponser;

    /**
     * Registrar um novo usuário (usado pelo admin do tenant).
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'unique:users'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
            'tenant_id' => ['required', 'exists:tenants,id'],
            'role'      => ['sometimes', 'string', 'in:admin,driver,manager'],
        ]);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'tenant_id' => $validated['tenant_id'],
            'role'      => $validated['role'] ?? 'driver',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->successResponse([
            'user'  => $user,
            'token' => $token,
        ], 'Usuário registrado com sucesso!', 201);
    }

    /**
     * Login — gera token Sanctum para o App Flutter.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais estão incorretas.'],
            ]);
        }

        // Revoga tokens anteriores (single device)
        $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->successResponse([
            'user'  => $user,
            'token' => $token,
        ], 'Login realizado com sucesso!');
    }

    /**
     * Logout — revoga o token atual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout realizado com sucesso!');
    }

    /**
     * Retorna o perfil do usuário autenticado.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            $request->user()->load('tenant'),
            'Perfil carregado.'
        );
    }
}
