<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\AddressService;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends ApiController
{
    public function __construct(
        private readonly AddressService $addressService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection(
            $this->addressService->allForUser($request->user()),
        )->additional([
            'success' => true,
            'message' => 'Daftar alamat berhasil diambil.',
        ]);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addressService->create(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            new AddressResource($address),
            'Alamat berhasil dibuat.',
            201,
        );
    }

    public function show(Request $request, Address $address): JsonResponse
    {
        $address = $this->addressService->show(
            $request->user(),
            $address,
        );

        return $this->success(
            new AddressResource($address),
            'Detail alamat berhasil diambil.',
        );
    }

    public function update(
        UpdateAddressRequest $request,
        Address $address,
    ): JsonResponse {
        $address = $this->addressService->update(
            $request->user(),
            $address,
            $request->validated(),
        );

        return $this->success(
            new AddressResource($address),
            'Alamat berhasil diperbarui.',
        );
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->addressService->delete($request->user(), $address);

        return $this->noContent('Alamat berhasil dihapus.');
    }
}
