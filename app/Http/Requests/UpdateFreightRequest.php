<?php

namespace App\Http\Requests;

use App\Enums\TrailerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateFreightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'driver_id'              => ['sometimes', 'exists:users,id'],
            'truck_id'               => ['nullable', 'exists:trucks,id'],
            'trailer_id'             => ['nullable', 'exists:trailers,id'],

            'cargo_name'             => ['sometimes', 'string', 'max:255'],
            'cargo_description'      => ['nullable', 'string', 'max:2000'],
            'weight'                 => ['sometimes', 'numeric', 'min:0.01', 'max:99999'],
            'is_hazardous'           => ['sometimes', 'boolean'],
            'is_fragile'             => ['sometimes', 'boolean'],
            'requires_refrigeration' => ['sometimes', 'boolean'],

            'required_trailer_type'  => ['nullable', new Enum(TrailerType::class)],
            'required_hitch_type'    => ['nullable', 'string', 'in:fifth_wheel,pintle,drawbar'],

            'origin_address'         => ['sometimes', 'string', 'max:500'],
            'destination_address'    => ['sometimes', 'string', 'max:500'],
            'origin_lat'             => ['sometimes', 'numeric', 'between:-90,90'],
            'origin_lng'             => ['sometimes', 'numeric', 'between:-180,180'],
            'destination_lat'        => ['sometimes', 'numeric', 'between:-90,90'],
            'destination_lng'        => ['sometimes', 'numeric', 'between:-180,180'],

            'distance_km'            => ['nullable', 'numeric', 'min:0'],
            'estimated_hours'        => ['nullable', 'integer', 'min:1'],

            'price_per_km'           => ['nullable', 'numeric', 'min:0'],
            'price_per_ton'          => ['nullable', 'numeric', 'min:0'],
            'toll_cost'              => ['nullable', 'numeric', 'min:0'],
            'fuel_cost'              => ['nullable', 'numeric', 'min:0'],

            'deadline_at'            => ['nullable', 'date', 'after:now'],
        ];
    }
}
