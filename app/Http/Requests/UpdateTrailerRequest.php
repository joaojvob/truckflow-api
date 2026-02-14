<?php

namespace App\Http\Requests;

use App\Enums\TrailerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTrailerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plate'      => ['sometimes', 'string', 'max:10', 'unique:trailers,plate,' . $this->route('trailer')?->id],
            'renavam'    => ['nullable', 'string', 'max:20'],
            'type'       => ['sometimes', new Enum(TrailerType::class)],
            'brand'      => ['nullable', 'string', 'max:100'],
            'model'      => ['nullable', 'string', 'max:100'],
            'year'       => ['nullable', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'axle_count' => ['sometimes', 'integer', 'in:2,3'],
            'max_weight' => ['sometimes', 'numeric', 'min:0'],
            'length'     => ['nullable', 'numeric', 'min:0'],
            'hitch_type' => ['sometimes', 'string', 'in:fifth_wheel,pintle,drawbar'],
            'driver_id'  => ['nullable', 'exists:users,id'],
        ];
    }
}
