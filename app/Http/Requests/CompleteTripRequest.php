<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDriver();
    }

    public function rules(): array
    {
        return [
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ];
    }
}
