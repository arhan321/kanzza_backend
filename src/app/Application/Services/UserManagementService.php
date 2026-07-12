<?php

namespace App\Application\Services;

use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->users->paginate(
            $filters,
            min((int) ($filters['per_page'] ?? 15), 100),
        );
    }

    public function createStaff(array $data): User
    {
        return $this->users->create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::from($data['role']),
            'status' => UserStatus::Active,
        ]);
    }

    public function updateRole(User $actor, User $target, UserRole $role): User
    {
        $this->preventSelfModification($actor, $target);

        if (! in_array($role, [UserRole::Cashier, UserRole::Driver], true)) {
            throw ValidationException::withMessages([
                'role' => ['Owner hanya dapat mengatur role cashier atau driver melalui endpoint ini.'],
            ]);
        }

        return $this->users->update($target, ['role' => $role]);
    }

    public function updateStatus(User $actor, User $target, UserStatus $status): User
    {
        $this->preventSelfModification($actor, $target);

        return $this->users->update($target, ['status' => $status]);
    }

    private function preventSelfModification(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'user' => ['Owner tidak dapat mengubah role atau status akunnya sendiri.'],
            ]);
        }
    }
}
