<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashierTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'payment_amount' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
