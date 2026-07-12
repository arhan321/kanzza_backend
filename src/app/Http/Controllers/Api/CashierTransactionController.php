<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\OrderService;
use App\Domain\Enums\OrderChannel;
use App\Http\Requests\Order\StoreCashierTransactionRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashierTransactionController extends ApiController
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = array_merge($request->query(), [
            'channel' => OrderChannel::Cashier->value,
        ]);

        return OrderResource::collection(
            $this->orderService->paginateForUser(
                $request->user(),
                $filters,
            ),
        )->additional([
            'success' => true,
            'message' => 'Daftar transaksi kasir berhasil diambil.',
        ]);
    }

    public function store(StoreCashierTransactionRequest $request): JsonResponse
    {
        $order = $this->orderService->createCashierTransaction(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            new OrderResource($order),
            'Transaksi kasir berhasil disimpan.',
            201,
        );
    }
}
