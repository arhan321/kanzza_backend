<?php

namespace App\Application\Services;

use App\Domain\Repositories\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->products->paginate(
            $filters,
            min((int) ($filters['per_page'] ?? 15), 100),
        );
    }

    public function create(array $data): Product
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        if (($data['image'] ?? null) instanceof UploadedFile) {
            $data['image'] = $data['image']->store('products', 'public');
        }

        return $this->products->create($data);
    }

    public function update(Product $product, array $data): Product
    {
        if (array_key_exists('name', $data) && ! array_key_exists('slug', $data)) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (($data['image'] ?? null) instanceof UploadedFile) {
            if ($product->image !== null) {
                Storage::disk('public')->delete($product->image);
            }

            $data['image'] = $data['image']->store('products', 'public');
        } else {
            unset($data['image']);
        }

        return $this->products->update($product, $data);
    }

    public function delete(Product $product): void
    {
        if ($product->image !== null) {
            Storage::disk('public')->delete($product->image);
        }

        $this->products->delete($product);
    }
}
