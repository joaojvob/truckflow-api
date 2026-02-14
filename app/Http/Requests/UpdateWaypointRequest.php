<?php

namespace App\Http\Requests;

use App\Enums\WaypointType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateWaypointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy no controller
    }

    public function rules(): array
    {
        return [
            'name'                   => ['sometimes', 'string', 'max:255'],
            'description'            => ['nullable', 'string', 'max:1000'],
            'type'                   => ['sometimes', new Enum(WaypointType::class)],
            'lat'                    => ['sometimes', 'numeric', 'between:-90,90'],
            'lng'                    => ['sometimes', 'numeric', 'between:-180,180'],
            'address'                => ['nullable', 'string', 'max:500'],
            'order'                  => ['sometimes', 'integer', 'min:0'],
            'mandatory'              => ['sometimes', 'boolean'],
            'estimated_stop_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ];
    }
}
