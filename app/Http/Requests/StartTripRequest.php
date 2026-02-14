<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartTripRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return auth()->user()->isDriver();
    }

    /**
     * Regras de validação para o checklist e início da viagem.
     */
    public function rules(): array
    {
        return [
            'items'              => ['required', 'array'], 
            'items.pneus'        => ['required', 'boolean'],
            'items.oleo'         => ['required', 'boolean'],
            'items.luzes'        => ['required', 'boolean'],
            'items.documentacao' => ['required', 'boolean'],
        ];
    }
}