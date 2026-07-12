<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->fill($data);
        $user->save();

        return $user->refresh();
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where(
                    fn ($inner) => $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"),
                ),
            )
            ->when(
                $filters['role'] ?? null,
                fn ($query, string $role) => $query->where('role', $role),
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status),
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
