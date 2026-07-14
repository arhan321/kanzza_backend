<?php

namespace App\Http\Requests\Delivery;

use App\Enums\DeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    DeliveryStatus::PickedUp->value,
                    DeliveryStatus::OnDelivery->value,
                    DeliveryStatus::Delivered->value,
                ]),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'proof_image_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
