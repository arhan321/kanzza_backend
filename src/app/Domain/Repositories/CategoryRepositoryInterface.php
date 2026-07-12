<?php

namespace App\Domain\Repositories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CategoryRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Category;

    public function update(Category $category, array $data): Category;

    public function delete(Category $category): void;
}
