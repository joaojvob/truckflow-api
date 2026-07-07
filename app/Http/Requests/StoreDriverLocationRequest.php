<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lat'         => ['required', 'numeric', 'between:-90,90'],
            'lng'         => ['required', 'numeric', 'between:-180,180'],
            'speed_kmh'   => ['sometimes', 'numeric', 'min:0', 'max:300'],
            'heading'     => ['sometimes', 'numeric', 'min:0', 'max:360'],
            'recorded_at' => ['sometimes', 'date'],
        ];
    }
}
