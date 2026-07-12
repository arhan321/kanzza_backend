<?php

namespace App\Http\Requests\Address;

class UpdateAddressRequest extends StoreAddressRequest
{
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:60'],
            'recipient_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'full_address' => ['sometimes', 'required', 'string'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:15'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
