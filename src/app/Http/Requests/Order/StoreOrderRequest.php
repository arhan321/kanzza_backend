<?php

namespace App\Http\Requests\Order;

use App\Enums\DeliveryMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'address_id' => [
                Rule::requiredIf($this->input('delivery_method') === DeliveryMethod::Delivery->value),
                'nullable',
                'integer',
                'exists:addresses,id',
            ],
            'distance_km' => [
                Rule::requiredIf($this->input('delivery_method') === DeliveryMethod::Delivery->value),
                'nullable',
                'numeric',
                'gt:0',
                'max:'.config('business.shipping.max_distance_km', 100),
            ],
            // Diterima agar kompatibel dengan Flutter, tetapi nominal selalu
            // dihitung ulang oleh backend dari distance_km.
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
