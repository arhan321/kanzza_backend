<?php

namespace App\Application\Services;

use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->categories->paginate(
            $filters,
            min((int) ($filters['per_page'] ?? 15), 100),
        );
    }

    public function create(array $data): Category
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return $this->categories->create($data);
    }

    public function update(Category $category, array $data): Category
    {
        if (array_key_exists('name', $data) && ! array_key_exists('slug', $data)) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $this->categories->update($category, $data);
    }

    public function delete(Category $category): void
    {
        $this->categories->delete($category);
    }
}
