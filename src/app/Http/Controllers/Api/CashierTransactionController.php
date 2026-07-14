<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderChannel;
use App\Http\Requests\Order\StoreCashierTransactionRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashierTransactionController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = array_merge($request->query(), [
            'channel' => OrderChannel::Cashier->value,
        ]);
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return OrderResource::collection(
            Order::query()
                ->with(['customer', 'cashier', 'items', 'latestPayment', 'delivery.driver'])
                ->visibleTo($request->user())
                ->filter($filters)
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
        )->additional([
            'success' => true,
            'message' => 'Daftar transaksi kasir berhasil diambil.',
        ]);
    }

    public function store(StoreCashierTransactionRequest $request): JsonResponse
    {
        $order = Order::createCashierTransaction(
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
