<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDopingTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDriver();
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'O arquivo do exame de doping é obrigatório.',
            'file.mimes'    => 'O arquivo deve ser PDF, JPG, JPEG ou PNG.',
            'file.max'      => 'O arquivo pode ter no máximo 10MB.',
        ];
    }
}
