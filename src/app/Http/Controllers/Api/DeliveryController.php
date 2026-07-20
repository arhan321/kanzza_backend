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
        Delivery::syncReadyOrders();

        return DeliveryResource::collection(
            Delivery::query()
                ->with(['order.items', 'order.customer', 'driver', 'codPaymentReceiver'])
                ->forDriver($driver)
                ->filter($request->query())
                ->orderByRaw(
                    'CASE WHEN status = ? THEN 0 ELSE 1 END',
                    [DeliveryStatus::Unassigned->value],
                )
                ->latest('updated_at')
                ->paginate($perPage)
                ->withQueryString(),
        )->additional([
            'success' => true,
            'message' => 'Daftar pengiriman berhasil diambil.',
        ]);
    }

    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        $delivery->ensureVisibleToDriver($request->user());
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

    public function claim(Request $request, Delivery $delivery): JsonResponse
    {
        $delivery = $delivery->claim($request->user());

        return $this->success(
            new DeliveryResource($delivery),
            'Pengiriman berhasil diambil. Silakan ambil pesanan dari toko.',
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
