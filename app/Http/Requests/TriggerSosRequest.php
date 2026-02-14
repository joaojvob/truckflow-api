<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TriggerSosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDriver();
    }

    public function rules(): array
    {
        return [
            'latitude'    => ['required', 'numeric', 'between:-90,90'],
            'longitude'   => ['required', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
