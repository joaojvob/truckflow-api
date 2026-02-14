<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTruckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plate'             => ['required', 'string', 'max:10', 'unique:trucks,plate'],
            'renavam'           => ['nullable', 'string', 'max:20'],
            'brand'             => ['required', 'string', 'max:100'],
            'model'             => ['required', 'string', 'max:100'],
            'year'              => ['required', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'color'             => ['nullable', 'string', 'max:50'],
            'axle_count'        => ['sometimes', 'integer', 'in:2,3,4,6'],
            'max_weight'        => ['required', 'numeric', 'min:0'],
            'has_trailer_hitch' => ['sometimes', 'boolean'],
            'hitch_type'        => ['nullable', 'required_if:has_trailer_hitch,true', 'string', 'in:fifth_wheel,pintle,drawbar'],
            'odometer'          => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
