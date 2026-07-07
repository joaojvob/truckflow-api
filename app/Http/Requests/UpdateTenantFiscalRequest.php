<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantFiscalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cnpj'         => ['required', 'string', 'size:14', 'regex:/^\d{14}$/'],
            'ie'           => ['required', 'string', 'max:20'],
            'razao_social' => ['required', 'string', 'max:255'],
            'uf'           => ['required', 'string', 'size:2', 'alpha'],
            'municipio'    => ['required', 'string', 'max:120'],
        ];
    }
}
