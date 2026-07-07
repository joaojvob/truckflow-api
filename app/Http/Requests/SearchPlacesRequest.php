<?php

namespace App\Http\Requests;

use App\Enums\PlaceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchPlacesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lat'           => ['sometimes', 'numeric', 'between:-90,90'],
            'lng'           => ['sometimes', 'numeric', 'between:-180,180'],
            'type'          => ['required', Rule::enum(PlaceType::class)],
            'radius_meters' => ['sometimes', 'integer', 'min:500', 'max:50000'],
        ];
    }
}
