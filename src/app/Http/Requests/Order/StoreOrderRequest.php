<?php

namespace App\Http\Requests\Order;

use App\Domain\Enums\DeliveryMethod;
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
