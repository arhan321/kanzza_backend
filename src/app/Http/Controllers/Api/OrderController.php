<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\OrderService;
use App\Domain\Enums\OrderStatus;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends ApiController
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $this->orderService->paginateForUser(
                $request->user(),
                $request->query(),
            ),
        )->additional([
            'success' => true,
            'message' => 'Daftar pesanan berhasil diambil.',
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createOnlineOrder(
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
        $order = $this->orderService->showForUser(
            $request->user(),
            $order,
        );

        return $this->success(
            new OrderResource($order),
            'Detail pesanan berhasil diambil.',
        );
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $order = $this->orderService->cancel(
            $request->user(),
            $order,
        );

        return $this->success(
            new OrderResource($order),
            'Pesanan berhasil dibatalkan.',
        );
    }

    public function updateStatus(
        UpdateOrderStatusRequest $request,
        Order $order,
    ): JsonResponse {
        $order = $this->orderService->updateStatus(
            $request->user(),
            $order,
            OrderStatus::from($request->validated('status')),
        );

        return $this->success(
            new OrderResource($order),
            'Status pesanan berhasil diperbarui.',
        );
    }
}
