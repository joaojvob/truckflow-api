<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewDopingTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'approved' => ['required', 'boolean'],
            'notes'    => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'approved.required' => 'O campo aprovado é obrigatório.',
            'approved.boolean'  => 'O campo aprovado deve ser verdadeiro ou falso.',
            'notes.max'         => 'As observações podem ter no máximo 2000 caracteres.',
        ];
    }
}
