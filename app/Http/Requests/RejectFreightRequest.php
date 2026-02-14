<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectFreightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDriver();
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.max' => 'O motivo pode ter no máximo 1000 caracteres.',
        ];
    }
}
