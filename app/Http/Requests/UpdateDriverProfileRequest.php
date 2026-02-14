<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'phone'                    => ['nullable', 'string', 'max:20'],
            'cpf'                      => ['nullable', 'string', 'max:14', "unique:driver_profiles,cpf,{$userId},user_id"],
            'birth_date'               => ['nullable', 'date', 'before:-18 years'],
            'cnh_number'               => ['nullable', 'string', 'max:20'],
            'cnh_category'             => ['nullable', 'string', 'in:A,B,C,D,E,AB,AC,AD,AE'],
            'cnh_expiry'               => ['nullable', 'date'],
            'address'                  => ['nullable', 'string', 'max:255'],
            'city'                     => ['nullable', 'string', 'max:100'],
            'state'                    => ['nullable', 'string', 'size:2'],
            'zip_code'                 => ['nullable', 'string', 'max:10'],
            'emergency_contact_name'   => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone'  => ['nullable', 'string', 'max:20'],
        ];
    }
}
