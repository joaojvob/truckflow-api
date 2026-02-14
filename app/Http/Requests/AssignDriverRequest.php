<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'driver_id.required' => 'O motorista é obrigatório.',
            'driver_id.exists'   => 'Motorista não encontrado.',
        ];
    }
}
