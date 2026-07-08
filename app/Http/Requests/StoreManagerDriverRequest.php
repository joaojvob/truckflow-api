<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManagerDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->isAdmin() || $user->isManager();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'cpf'          => ['nullable', 'string', 'max:14'],
            'cnh_number'   => ['nullable', 'string', 'max:20'],
            'cnh_category' => ['nullable', 'string', 'max:5'],
            'cnh_expiry'   => ['nullable', 'date'],
        ];
    }
}
