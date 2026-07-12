<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'description' => ['nullable', 'string'],
            'cost_price' => ['sometimes', 'required', 'integer', 'min:0'],
            'selling_price' => ['sometimes', 'required', 'integer', 'min:1'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'minimum_stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
