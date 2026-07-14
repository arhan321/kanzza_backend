<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddressController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection(
            $request->user()
                ->addresses()
                ->orderByDesc('is_default')
                ->latest()
                ->get(),
        )->additional([
            'success' => true,
            'message' => 'Daftar alamat berhasil diambil.',
        ]);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $address = DB::transaction(function () use ($user, $data): Address {
            $isFirstAddress = $user->addresses()->doesntExist();

            if (($data['is_default'] ?? false) || $isFirstAddress) {
                $user->addresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            return $user->addresses()->create($data);
        });

        return $this->success(
            new AddressResource($address),
            'Alamat berhasil dibuat.',
            201,
        );
    }

    public function show(Request $request, Address $address): JsonResponse
    {
        $this->ensureOwnership($request, $address);

        return $this->success(
            new AddressResource($address),
            'Detail alamat berhasil diambil.',
        );
    }

    public function update(
        UpdateAddressRequest $request,
        Address $address,
    ): JsonResponse {
        $this->ensureOwnership($request, $address);
        $user = $request->user();
        $data = $request->validated();

        $address = DB::transaction(function () use ($user, $address, $data): Address {
            if ($data['is_default'] ?? false) {
                $user->addresses()->update(['is_default' => false]);
            }

            $address->update($data);

            return $address->refresh();
        });

        return $this->success(
            new AddressResource($address),
            'Alamat berhasil diperbarui.',
        );
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->ensureOwnership($request, $address);

        if ($address->is_default && $request->user()->addresses()->count() > 1) {
            throw ValidationException::withMessages([
                'address' => ['Alamat utama tidak dapat dihapus sebelum memilih alamat utama lain.'],
            ]);
        }

        $address->delete();

        return $this->noContent('Alamat berhasil dihapus.');
    }

    private function ensureOwnership(Request $request, Address $address): void
    {
        if ($address->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak memiliki akses ke alamat ini.');
        }
    }
}
