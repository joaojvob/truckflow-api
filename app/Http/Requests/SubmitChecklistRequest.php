<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDriver();
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Os itens do checklist são obrigatórios.',
            'items.array'    => 'Os itens devem ser um array.',
            'items.min'      => 'O checklist deve ter pelo menos 1 item.',
        ];
    }
}
