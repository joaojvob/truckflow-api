<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Autenticação de usuários via Laravel Sanctum (token bearer).
 */
class AuthService
{
    /**
     * Registra um novo usuário e emite token de acesso.
     *
     * @param  array{name: string, email: string, password: string, tenant_id?: int|null, role?: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'tenant_id' => $data['tenant_id'],
            'role'      => $data['role'] ?? UserRole::Driver,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * Autentica por e-mail/senha e emite novo token (revoga sessões anteriores).
     *
     * @param  string  $email  E-mail do usuário.
     * @param  string  $password  Senha em texto plano.
     * @return array{user: User, token: string}
     *
     * @throws ValidationException Credenciais inválidas.
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
