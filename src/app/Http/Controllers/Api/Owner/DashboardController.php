<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        $today = Carbon::today();
        $topProducts = OrderItem::query()
            ->select([
                'product_id',
                'product_name',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(subtotal) as total_sales'),
            ])
            ->whereHas(
                'order',
                fn ($query) => $query->where('payment_status', PaymentStatus::Paid->value),
            )
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        return $this->success(
            [
                'today_revenue' => (int) Order::query()
                    ->where('payment_status', PaymentStatus::Paid->value)
                    ->whereDate('paid_at', $today)
                    ->sum('grand_total'),
                'today_transactions' => Order::query()
                    ->where('payment_status', PaymentStatus::Paid->value)
                    ->whereDate('paid_at', $today)
                    ->count(),
                'pending_orders' => Order::query()
                    ->whereIn('order_status', [
                        OrderStatus::Confirmed->value,
                        OrderStatus::Processing->value,
                        OrderStatus::Ready->value,
                    ])
                    ->count(),
                'low_stock_products' => Product::query()
                    ->whereColumn('stock', '<=', 'minimum_stock')
                    ->count(),
                'active_customers' => User::query()
                    ->where('role', 'customer')
                    ->where('status', 'active')
                    ->count(),
                'top_products' => $topProducts,
            ],
            'Ringkasan dashboard owner berhasil diambil.',
        );
    }
}
