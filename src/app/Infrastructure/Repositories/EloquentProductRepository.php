<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()
            ->with('category')
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where(
                    fn ($inner) => $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%"),
                ),
            )
            ->when(
                $filters['category_id'] ?? null,
                fn ($query, $categoryId) => $query->where('category_id', $categoryId),
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn ($query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOL)),
            )
            ->when(
                filter_var($filters['low_stock'] ?? false, FILTER_VALIDATE_BOOL),
                fn ($query) => $query->whereColumn('stock', '<=', 'minimum_stock'),
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Product
    {
        return Product::query()->create($data)->load('category');
    }

    public function update(Product $product, array $data): Product
    {
        $product->fill($data);
        $product->save();

        return $product->refresh()->load('category');
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function lockByIds(array $ids): Collection
    {
        return Product::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    public function save(Product $product): Product
    {
        $product->save();

        return $product;
    }
}
