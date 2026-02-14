<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'phone'                   => $this->phone,
            'cpf'                     => $this->cpf,
            'birth_date'              => $this->birth_date?->format('Y-m-d'),
            'cnh_number'              => $this->cnh_number,
            'cnh_category'            => $this->cnh_category,
            'cnh_expiry'              => $this->cnh_expiry?->format('Y-m-d'),
            'cnh_expired'             => $this->isCnhExpired(),
            'cnh_expiring_soon'       => $this->isCnhExpiringSoon(),
            'address'                 => $this->address,
            'city'                    => $this->city,
            'state'                   => $this->state,
            'zip_code'                => $this->zip_code,
            'emergency_contact_name'  => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'is_available'            => $this->is_available,
        ];
    }
}
