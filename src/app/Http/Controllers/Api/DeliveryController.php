<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\DeliveryService;
use App\Domain\Enums\DeliveryStatus;
use App\Http\Requests\Delivery\UpdateDeliveryStatusRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryController extends ApiController
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return DeliveryResource::collection(
            $this->deliveryService->paginateForDriver(
                $request->user(),
                $request->query(),
            ),
        )->additional([
            'success' => true,
            'message' => 'Daftar pengiriman berhasil diambil.',
        ]);
    }

    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        $delivery = $this->deliveryService->showForDriver(
            $request->user(),
            $delivery,
        );

        return $this->success(
            new DeliveryResource($delivery),
            'Detail pengiriman berhasil diambil.',
        );
    }

    public function updateStatus(
        UpdateDeliveryStatusRequest $request,
        Delivery $delivery,
    ): JsonResponse {
        $delivery = $this->deliveryService->updateDriverStatus(
            $request->user(),
            $delivery,
            DeliveryStatus::from($request->validated('status')),
            $request->validated(),
        );

        return $this->success(
            new DeliveryResource($delivery),
            'Status pengiriman berhasil diperbarui.',
        );
    }
}
