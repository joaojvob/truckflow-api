<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTruckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plate'             => ['sometimes', 'string', 'max:10', 'unique:trucks,plate,' . $this->route('truck')?->id],
            'renavam'           => ['nullable', 'string', 'max:20'],
            'brand'             => ['sometimes', 'string', 'max:100'],
            'model'             => ['sometimes', 'string', 'max:100'],
            'year'              => ['sometimes', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'color'             => ['nullable', 'string', 'max:50'],
            'axle_count'        => ['sometimes', 'integer', 'in:2,3,4,6'],
            'max_weight'        => ['sometimes', 'numeric', 'min:0'],
            'has_trailer_hitch' => ['sometimes', 'boolean'],
            'hitch_type'        => ['nullable', 'string', 'in:fifth_wheel,pintle,drawbar'],
            'odometer'          => ['sometimes', 'integer', 'min:0'],
            'driver_id'         => ['nullable', 'exists:users,id'],
        ];
    }
}
