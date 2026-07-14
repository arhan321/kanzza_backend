<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return OrderResource::collection(
            Order::query()
                ->with(['customer', 'cashier', 'items', 'latestPayment', 'delivery.driver'])
                ->visibleTo($request->user())
                ->filter($request->query())
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
        )->additional([
            'success' => true,
            'message' => 'Daftar pesanan berhasil diambil.',
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = Order::createOnline(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            new OrderResource($order),
            'Pesanan berhasil dibuat. Silakan lanjutkan pembayaran.',
            201,
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $order->ensureVisibleTo($request->user());
        $order->load([
            'customer',
            'cashier',
            'items.product',
            'payments',
            'latestPayment',
            'delivery.driver',
        ]);

        return $this->success(
            new OrderResource($order),
            'Detail pesanan berhasil diambil.',
        );
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $order = $order->cancelBy($request->user());

        return $this->success(
            new OrderResource($order),
            'Pesanan berhasil dibatalkan.',
        );
    }

    public function updateStatus(
        UpdateOrderStatusRequest $request,
        Order $order,
    ): JsonResponse {
        $order = $order->transitionTo(
            $request->user(),
            OrderStatus::from($request->validated('status')),
        );

        return $this->success(
            new OrderResource($order),
            'Status pesanan berhasil diperbarui.',
        );
    }
}
