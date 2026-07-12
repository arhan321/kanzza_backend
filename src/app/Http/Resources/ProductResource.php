<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageUrl = null;

        if ($this->image !== null) {
            $imageUrl = Str::startsWith($this->image, ['http://', 'https://'])
                ? $this->image
                : url(Storage::url($this->image));
        }

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'cost_price' => $this->when(
                $request->user()?->isRole('owner'),
                $this->cost_price,
            ),
            'selling_price' => $this->selling_price,
            'stock' => $this->stock,
            'minimum_stock' => $this->minimum_stock,
            'is_low_stock' => $this->isLowStock(),
            'unit' => $this->unit,
            'image_url' => $imageUrl,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
