<?php

namespace App\Domain\Repositories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface AddressRepositoryInterface
{
    /**
     * @return Collection<int, Address>
     */
    public function allForUser(User $user): Collection;

    public function create(User $user, array $data): Address;

    public function update(Address $address, array $data): Address;

    public function delete(Address $address): void;

    public function clearDefaultForUser(User $user): void;
}
