<?php

namespace App\Domain\Repositories;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Product;

    public function update(Product $product, array $data): Product;

    public function delete(Product $product): void;

    /**
     * @param list<int> $ids
     * @return Collection<int, Product>
     */
    public function lockByIds(array $ids): Collection;

    public function save(Product $product): Product;
}
