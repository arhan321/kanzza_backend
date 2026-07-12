<?php

namespace App\Domain\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;
}
