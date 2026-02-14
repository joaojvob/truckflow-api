<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Qualquer usuário logado pode criar empresa
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'slug'     => ['required', 'string', 'max:100', 'unique:tenants,slug', 'alpha_dash'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
