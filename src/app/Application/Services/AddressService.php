<?php

namespace App\Application\Services;

use App\Domain\Repositories\AddressRepositoryInterface;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddressService
{
    public function __construct(
        private readonly AddressRepositoryInterface $addresses,
    ) {
    }

    /**
     * @return Collection<int, Address>
     */
    public function allForUser(User $user): Collection
    {
        return $this->addresses->allForUser($user);
    }


    public function show(User $user, Address $address): Address
    {
        $this->ensureOwnership($user, $address);

        return $address;
    }

    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data): Address {
            $isFirstAddress = $user->addresses()->doesntExist();

            if (($data['is_default'] ?? false) || $isFirstAddress) {
                $this->addresses->clearDefaultForUser($user);
                $data['is_default'] = true;
            }

            return $this->addresses->create($user, $data);
        });
    }

    public function update(User $user, Address $address, array $data): Address
    {
        $this->ensureOwnership($user, $address);

        return DB::transaction(function () use ($user, $address, $data): Address {
            if ($data['is_default'] ?? false) {
                $this->addresses->clearDefaultForUser($user);
            }

            return $this->addresses->update($address, $data);
        });
    }

    public function delete(User $user, Address $address): void
    {
        $this->ensureOwnership($user, $address);

        if ($address->is_default && $user->addresses()->count() > 1) {
            throw ValidationException::withMessages([
                'address' => ['Alamat utama tidak dapat dihapus sebelum memilih alamat utama lain.'],
            ]);
        }

        $this->addresses->delete($address);
    }

    private function ensureOwnership(User $user, Address $address): void
    {
        if ($address->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses ke alamat ini.');
        }
    }
}
