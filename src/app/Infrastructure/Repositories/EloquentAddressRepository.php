<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\AddressRepositoryInterface;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EloquentAddressRepository implements AddressRepositoryInterface
{
    public function allForUser(User $user): Collection
    {
        return $user->addresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get();
    }

    public function create(User $user, array $data): Address
    {
        return $user->addresses()->create($data);
    }

    public function update(Address $address, array $data): Address
    {
        $address->fill($data);
        $address->save();

        return $address->refresh();
    }

    public function delete(Address $address): void
    {
        $address->delete();
    }

    public function clearDefaultForUser(User $user): void
    {
        $user->addresses()->update(['is_default' => false]);
    }
}
