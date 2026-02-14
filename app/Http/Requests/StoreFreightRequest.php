<?php

namespace App\Http\Requests;

use App\Enums\TrailerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreFreightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'driver_id'              => ['required', 'exists:users,id'],
            'truck_id'               => ['nullable', 'exists:trucks,id'],
            'trailer_id'             => ['nullable', 'exists:trailers,id'],

            // Carga
            'cargo_name'             => ['required', 'string', 'max:255'],
            'cargo_description'      => ['nullable', 'string', 'max:2000'],
            'weight'                 => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'is_hazardous'           => ['sometimes', 'boolean'],
            'is_fragile'             => ['sometimes', 'boolean'],
            'requires_refrigeration' => ['sometimes', 'boolean'],

            // Requisitos de veículo
            'required_trailer_type'  => ['nullable', new Enum(TrailerType::class)],
            'required_hitch_type'    => ['nullable', 'string', 'in:fifth_wheel,pintle,drawbar'],

            // Endereços e coordenadas
            'origin_address'         => ['required', 'string', 'max:500'],
            'destination_address'    => ['required', 'string', 'max:500'],
            'origin_lat'             => ['required', 'numeric', 'between:-90,90'],
            'origin_lng'             => ['required', 'numeric', 'between:-180,180'],
            'destination_lat'        => ['required', 'numeric', 'between:-90,90'],
            'destination_lng'        => ['required', 'numeric', 'between:-180,180'],

            // Distância e tempo
            'distance_km'            => ['nullable', 'numeric', 'min:0'],
            'estimated_hours'        => ['nullable', 'integer', 'min:1'],

            // Preço
            'price_per_km'           => ['nullable', 'numeric', 'min:0'],
            'price_per_ton'          => ['nullable', 'numeric', 'min:0'],
            'toll_cost'              => ['nullable', 'numeric', 'min:0'],
            'fuel_cost'              => ['nullable', 'numeric', 'min:0'],

            // Prazo
            'deadline_at'            => ['nullable', 'date', 'after:now'],
        ];
    }
}
