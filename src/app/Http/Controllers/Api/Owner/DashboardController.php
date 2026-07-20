<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $period = in_array($request->query('period'), ['today', 'week', 'month'], true)
            ? (string) $request->query('period')
            : 'today';
        [$start, $end] = $this->periodRange($period);
        [$previousStart, $previousEnd] = $this->previousRange($start, $end);

        $paidOrders = Order::query()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$start, $end]);
        $previousPaidOrders = Order::query()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$previousStart, $previousEnd]);

        $revenue = (int) (clone $paidOrders)->sum('grand_total');
        $transactions = (clone $paidOrders)->count();
        $previousRevenue = (int) $previousPaidOrders->sum('grand_total');
        $itemsSold = (int) OrderItem::query()
            ->whereHas(
                'order',
                fn (Builder $query) => $query
                    ->where('payment_status', PaymentStatus::Paid->value)
                    ->whereBetween('paid_at', [$start, $end]),
            )
            ->sum('quantity');
        $currentStock = (int) Product::query()->where('is_active', true)->sum('stock');
        $customerMetrics = $this->customerMetrics($start, $end);

        return $this->success([
            'period' => [
                'key' => $period,
                'start' => $start->toISOString(),
                'end' => $end->toISOString(),
            ],
            'revenue' => $revenue,
            'transactions' => $transactions,
            'average_order' => $transactions === 0
                ? 0
                : (int) round($revenue / $transactions),
            'revenue_growth_percent' => $this->growth($revenue, $previousRevenue),
            'items_sold' => $itemsSold,
            'customer_retention_percent' => $customerMetrics['retention_percent'],
            'active_customers' => $customerMetrics['active_customers'],
            'repeat_customers' => $customerMetrics['repeat_customers'],
            'new_customers' => $customerMetrics['new_customers'],
            'stock_turnover' => $currentStock === 0
                ? 0
                : round($itemsSold / $currentStock, 2),
            'total_products' => Product::query()->count(),
            'pending_orders' => Order::query()
                ->whereIn('order_status', [
                    OrderStatus::Confirmed->value,
                    OrderStatus::Processing->value,
                    OrderStatus::Ready->value,
                ])
                ->count(),
            'low_stock_products' => $this->lowStockProducts(),
            'sales_trend' => $this->salesTrend($period, $start, $end),
            'category_sales' => $this->categorySales($start, $end),
            'top_products' => $this->topProducts($start, $end),
            'recent_orders' => $this->recentOrders(),
        ], 'Ringkasan dashboard owner berhasil diambil.');
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'week' => [$now->copy()->startOfWeek(), $now->copy()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()],
            default => [$now->copy()->startOfDay(), $now->copy()],
        };
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function previousRange(Carbon $start, Carbon $end): array
    {
        $seconds = max(1, (int) $start->diffInSeconds($end));
        $previousEnd = $start->copy()->subSecond();

        return [$previousEnd->copy()->subSeconds($seconds), $previousEnd];
    }

    /** @return array<string, int|float> */
    private function customerMetrics(Carbon $start, Carbon $end): array
    {
        $customerIds = Order::query()
            ->where('channel', OrderChannel::Online->value)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$start, $end])
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id');

        $activeCustomers = $customerIds->count();
        $repeatCustomers = $customerIds->isEmpty()
            ? 0
            : User::query()
                ->whereIn('id', $customerIds)
                ->whereHas(
                    'customerOrders',
                    fn (Builder $query) => $query->where(
                        'payment_status',
                        PaymentStatus::Paid->value,
                    ),
                    '>=',
                    2,
                )
                ->count();

        return [
            'active_customers' => $activeCustomers,
            'repeat_customers' => $repeatCustomers,
            'new_customers' => User::query()
                ->where('role', UserRole::Customer->value)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'retention_percent' => $activeCustomers === 0
                ? 0
                : round(($repeatCustomers / $activeCustomers) * 100, 1),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function salesTrend(string $period, Carbon $start, Carbon $end): Collection
    {
        $orders = Order::query()
            ->select(['paid_at', 'grand_total'])
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$start, $end])
            ->get();

        if ($period === 'today') {
            return collect(range(0, 23, 3))->map(function (int $hour) use ($orders): array {
                $nextHour = min(24, $hour + 3);
                return [
                    'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
                    'value' => (int) $orders
                        ->filter(function (Order $order) use ($hour, $nextHour): bool {
                            $paidHour = $order->paid_at?->hour ?? -1;
                            return $paidHour >= $hour && $paidHour < $nextHour;
                        })
                        ->sum('grand_total'),
                ];
            });
        }

        $days = $start->copy()->startOfDay()->daysUntil($end->copy()->startOfDay()->addDay());

        return collect($days)->map(function (Carbon $day) use ($orders, $period): array {
            return [
                'label' => $period === 'week'
                    ? $this->dayLabel($day)
                    : $day->format('d M'),
                'value' => (int) $orders
                    ->filter(fn (Order $order): bool => $order->paid_at?->isSameDay($day) === true)
                    ->sum('grand_total'),
            ];
        })->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function categorySales(Carbon $start, Carbon $end): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereBetween('orders.paid_at', [$start, $end])
            ->selectRaw("COALESCE(categories.name, 'Tanpa Kategori') as name")
            ->selectRaw('SUM(order_items.quantity) as total_quantity')
            ->selectRaw('SUM(order_items.subtotal) as total_sales')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_sales')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) $row->name,
                'total_quantity' => (int) $row->total_quantity,
                'total_sales' => (int) $row->total_sales,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function topProducts(Carbon $start, Carbon $end): Collection
    {
        return OrderItem::query()
            ->select([
                'product_id',
                'product_name',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(subtotal) as total_sales'),
            ])
            ->whereHas(
                'order',
                fn (Builder $query) => $query
                    ->where('payment_status', PaymentStatus::Paid->value)
                    ->whereBetween('paid_at', [$start, $end]),
            )
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'product_id' => $row->product_id,
                'product_name' => (string) $row->product_name,
                'total_quantity' => (int) $row->total_quantity,
                'total_sales' => (int) $row->total_sales,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function lowStockProducts(): Collection
    {
        return Product::query()
            ->select(['id', 'name', 'stock', 'minimum_stock', 'unit'])
            ->where('is_active', true)
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->orderBy('stock')
            ->limit(10)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'stock' => $product->stock,
                'minimum_stock' => $product->minimum_stock,
                'unit' => $product->unit,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function recentOrders(): Collection
    {
        return Order::query()
            ->with(['customer:id,name', 'cashier:id,name'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'name' => $order->customer?->name
                    ?? $order->cashier?->name
                    ?? 'Pelanggan Offline',
                'channel' => $order->channel->value,
                'order_status' => $order->order_status->value,
                'payment_status' => $order->payment_status->value,
                'grand_total' => $order->grand_total,
                'created_at' => $order->created_at?->toISOString(),
            ]);
    }

    private function growth(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function dayLabel(Carbon $day): string
    {
        return match ($day->dayOfWeek) {
            Carbon::MONDAY => 'Sen',
            Carbon::TUESDAY => 'Sel',
            Carbon::WEDNESDAY => 'Rab',
            Carbon::THURSDAY => 'Kam',
            Carbon::FRIDAY => 'Jum',
            Carbon::SATURDAY => 'Sab',
            default => 'Min',
        };
    }
}
