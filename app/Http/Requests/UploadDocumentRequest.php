<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'        => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'crlv_expiry' => ['sometimes', 'nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'     => 'O arquivo do documento é obrigatório.',
            'file.mimes'        => 'O arquivo deve ser PDF, JPG, JPEG ou PNG.',
            'file.max'          => 'O arquivo pode ter no máximo 10MB.',
            'crlv_expiry.after' => 'A validade do CRLV deve ser uma data futura.',
        ];
    }
}
