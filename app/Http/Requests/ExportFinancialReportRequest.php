<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportFinancialReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isAdmin() || auth()->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'from'   => ['sometimes', 'date'],
            'to'     => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }

    public function messages(): array
    {
        return [
            'format.required' => 'Informe o formato de exportação (pdf ou xlsx).',
            'format.in'       => 'Formato inválido. Use pdf ou xlsx.',
        ];
    }
}
