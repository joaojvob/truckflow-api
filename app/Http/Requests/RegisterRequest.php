<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'unique:users'],
            'password'  => ['required', 'string', Password::defaults(), 'confirmed'],
            'tenant_id' => ['required', 'exists:tenants,id'],
            'role'      => ['sometimes', new Enum(UserRole::class)],
        ];
    }
}
