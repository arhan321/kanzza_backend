<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Category::query()
            ->withCount('products')
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"),
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn ($query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOL)),
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Category
    {
        return Category::query()->create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->fill($data);
        $category->save();

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
