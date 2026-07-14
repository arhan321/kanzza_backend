<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeliveryStatus;
use App\Enums\UserRole;
use App\Http\Requests\Delivery\UpdateDeliveryStatusRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $driver = $request->user();

        if (! $driver->isRole(UserRole::Driver)) {
            abort(403, 'Hanya driver yang dapat melihat daftar pengiriman ini.');
        }

        $perPage = min((int) $request->query('per_page', 15), 100);

        return DeliveryResource::collection(
            Delivery::query()
                ->with(['order.items', 'order.customer', 'driver', 'codPaymentReceiver'])
                ->forDriver($driver)
                ->filter($request->query())
                ->latest('assigned_at')
                ->paginate($perPage)
                ->withQueryString(),
        )->additional([
            'success' => true,
            'message' => 'Daftar pengiriman berhasil diambil.',
        ]);
    }

    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        $delivery->ensureAssignedTo($request->user());
        $delivery->load([
            'order.items',
            'order.customer',
            'driver',
            'codPaymentReceiver',
        ]);

        return $this->success(
            new DeliveryResource($delivery),
            'Detail pengiriman berhasil diambil.',
        );
    }

    public function updateStatus(
        UpdateDeliveryStatusRequest $request,
        Delivery $delivery,
    ): JsonResponse {
        $delivery = $delivery->transitionTo(
            $request->user(),
            DeliveryStatus::from($request->validated('status')),
            $request->validated(),
        );

        return $this->success(
            new DeliveryResource($delivery),
            'Status pengiriman berhasil diperbarui.',
        );
    }
}
