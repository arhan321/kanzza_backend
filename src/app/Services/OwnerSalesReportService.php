<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OwnerSalesReportService
{
    public function query(
        Carbon $start,
        Carbon $end,
        ?string $channel = null,
    ): Builder {
        return Order::query()
            ->with(['customer', 'cashier', 'items'])
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->when(
                $channel,
                fn (Builder $query, string $value) => $query->where('channel', $value),
            )
            ->latest('paid_at');
    }

    /**
     * @return array<string, int|float>
     */
    public function summary(Builder $query): array
    {
        $totalTransactions = (clone $query)->count();
        $totalRevenue = (int) (clone $query)->sum('grand_total');
        $orderIds = (clone $query)->reorder()->select('orders.id');
        $totalItems = (int) OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->sum('quantity');

        return [
            'total_transactions' => $totalTransactions,
            'total_revenue' => $totalRevenue,
            'total_items' => $totalItems,
            'average_order' => $totalTransactions === 0
                ? 0
                : (int) round($totalRevenue / $totalTransactions),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Builder $query): Collection
    {
        return $query->get()->map(fn (Order $order): array => $this->mapOrder($order));
    }

    /**
     * @return array<string, mixed>
     */
    public function mapOrder(Order $order): array
    {
        $actorName = $order->channel->value === 'online'
            ? ($order->customer?->name ?? 'Customer')
            : ($order->cashier?->name ?? 'Pelanggan Offline');

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'channel' => $order->channel->value,
            'order_status' => $order->order_status->value,
            'payment_status' => $order->payment_status->value,
            'payment_method' => $order->payment_method->value,
            'customer_name' => $actorName,
            'total_quantity' => (int) $order->items->sum('quantity'),
            'grand_total' => $order->grand_total,
            'paid_at' => $order->paid_at?->toISOString(),
            'created_at' => $order->created_at?->toISOString(),
        ];
    }
}
