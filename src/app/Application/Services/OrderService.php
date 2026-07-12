<?php

namespace App\Application\Services;

use App\Domain\Enums\DeliveryMethod;
use App\Domain\Enums\OrderChannel;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentMethod;
use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\StockMovementType;
use App\Domain\Enums\UserRole;
use App\Domain\Repositories\AddressRepositoryInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\StockMovementRepositoryInterface;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly AddressRepositoryInterface $addresses,
        private readonly StockMovementRepositoryInterface $stockMovements,
    ) {
    }

    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        return $this->orders->paginateForUser(
            $user,
            $filters,
            min((int) ($filters['per_page'] ?? 15), 100),
        );
    }

    public function createOnlineOrder(User $customer, array $data): Order
    {
        if (! $customer->isRole(UserRole::Customer)) {
            throw ValidationException::withMessages([
                'user' => ['Hanya customer yang dapat membuat pesanan online.'],
            ]);
        }

        return DB::transaction(function () use ($customer, $data): Order {
            $address = $this->resolveAddress($customer, $data);
            $items = $this->normalizeItems($data['items']);
            $products = $this->products->lockByIds($items->keys()->all());

            $calculation = $this->calculateAndValidateItems($items, $products);
            $shippingCost = $data['delivery_method'] === DeliveryMethod::Pickup->value
                ? 0
                : (int) config('business.shipping.base_cost', 10000);

            $order = $this->orders->create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer->id,
                'cashier_id' => null,
                'channel' => OrderChannel::Online,
                'order_status' => OrderStatus::PendingPayment,
                'payment_status' => PaymentStatus::Unpaid,
                'delivery_method' => $data['delivery_method'],
                'payment_method' => PaymentMethod::Midtrans,
                'subtotal' => $calculation['subtotal'],
                'shipping_cost' => $shippingCost,
                'discount' => 0,
                'grand_total' => $calculation['subtotal'] + $shippingCost,
                'address_snapshot' => $address?->toSnapshot(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->persistItemsAndReserveStock(
                order: $order,
                actor: $customer,
                normalizedItems: $items,
                lockedProducts: $products,
                movementType: StockMovementType::Reservation,
            );

            return $order->load([
                'customer',
                'cashier',
                'items.product',
                'latestPayment',
                'delivery.driver',
            ]);
        }, 3);
    }

    public function createCashierTransaction(User $cashier, array $data): Order
    {
        if (! $cashier->isRole(UserRole::Cashier, UserRole::Owner)) {
            abort(403, 'Hanya cashier atau owner yang dapat membuat transaksi kasir.');
        }

        return DB::transaction(function () use ($cashier, $data): Order {
            $items = $this->normalizeItems($data['items']);
            $products = $this->products->lockByIds($items->keys()->all());
            $calculation = $this->calculateAndValidateItems($items, $products);
            $paymentAmount = (int) $data['payment_amount'];

            if ($paymentAmount < $calculation['subtotal']) {
                throw ValidationException::withMessages([
                    'payment_amount' => ['Nominal pembayaran kurang dari total transaksi.'],
                ]);
            }

            $order = $this->orders->create([
                'order_number' => $this->generateOrderNumber('POS'),
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => $cashier->id,
                'channel' => OrderChannel::Cashier,
                'order_status' => OrderStatus::Confirmed,
                'payment_status' => PaymentStatus::Paid,
                'delivery_method' => DeliveryMethod::Pickup,
                'payment_method' => PaymentMethod::Cash,
                'subtotal' => $calculation['subtotal'],
                'shipping_cost' => 0,
                'discount' => 0,
                'grand_total' => $calculation['subtotal'],
                'payment_amount' => $paymentAmount,
                'change_amount' => $paymentAmount - $calculation['subtotal'],
                'address_snapshot' => null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
            ]);

            $this->persistItemsAndReserveStock(
                order: $order,
                actor: $cashier,
                normalizedItems: $items,
                lockedProducts: $products,
                movementType: StockMovementType::Sale,
            );

            return $order->load([
                'customer',
                'cashier',
                'items.product',
                'latestPayment',
                'delivery.driver',
            ]);
        }, 3);
    }

    public function showForUser(User $user, Order $order): Order
    {
        $this->ensureCanView($user, $order);

        return $order->load([
            'customer',
            'cashier',
            'items.product',
            'payments',
            'latestPayment',
            'delivery.driver',
        ]);
    }

    public function cancel(User $actor, Order $order): Order
    {
        $this->ensureCanView($actor, $order);

        if ($order->isPaid()) {
            throw ValidationException::withMessages([
                'order' => ['Pesanan yang sudah dibayar tidak dapat dibatalkan melalui endpoint ini.'],
            ]);
        }

        if ($order->order_status === OrderStatus::Cancelled) {
            return $order->load(['items', 'latestPayment']);
        }

        return DB::transaction(function () use ($actor, $order): Order {
            $order->loadMissing('items');
            $productIds = $order->items->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->all();
            $lockedProducts = $this->products->lockByIds($productIds);

            foreach ($order->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                /** @var Product|null $product */
                $product = $lockedProducts->get($item->product_id);

                if ($product === null) {
                    continue;
                }

                $stockBefore = $product->stock;
                $product->stock += $item->quantity;
                $this->products->save($product);

                $this->stockMovements->create([
                    'product_id' => $product->id,
                    'user_id' => $actor->id,
                    'type' => StockMovementType::Restoration,
                    'quantity' => $item->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->stock,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'notes' => 'Pengembalian stok karena pesanan dibatalkan.',
                ]);
            }

            $updated = $this->orders->update($order, [
                'order_status' => OrderStatus::Cancelled,
                'payment_status' => PaymentStatus::Cancelled,
            ]);

            return $updated->load([
                'customer',
                'cashier',
                'items.product',
                'latestPayment',
                'delivery.driver',
            ]);
        }, 3);
    }

    public function updateStatus(User $actor, Order $order, OrderStatus $targetStatus): Order
    {
        if (! $actor->isRole(UserRole::Cashier, UserRole::Owner)) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah status pesanan.');
        }

        $currentStatus = $order->order_status;

        if ($currentStatus === $targetStatus) {
            return $order->load(['items', 'customer', 'delivery.driver']);
        }

        $allowedTransitions = [
            OrderStatus::Confirmed->value => [
                OrderStatus::Processing,
                OrderStatus::Cancelled,
            ],
            OrderStatus::Processing->value => [
                OrderStatus::Ready,
                OrderStatus::Cancelled,
            ],
            OrderStatus::Ready->value => [
                OrderStatus::Delivered,
                OrderStatus::Cancelled,
            ],
        ];

        $allowedTargets = $allowedTransitions[$currentStatus->value] ?? [];

        if (! in_array($targetStatus, $allowedTargets, true)) {
            throw ValidationException::withMessages([
                'status' => [
                    "Perubahan status dari {$currentStatus->value} ke {$targetStatus->value} tidak diizinkan.",
                ],
            ]);
        }

        if ($targetStatus === OrderStatus::Cancelled) {
            return $this->cancel($actor, $order);
        }

        if (
            $targetStatus === OrderStatus::Delivered
            && $order->delivery_method === DeliveryMethod::Delivery
        ) {
            throw ValidationException::withMessages([
                'status' => ['Pesanan delivery harus diselesaikan melalui alur driver.'],
            ]);
        }

        return $this->orders->update($order, [
            'order_status' => $targetStatus,
        ])->load(['items', 'customer', 'delivery.driver']);
    }

    public function ensureCanView(User $user, Order $order): void
    {
        if ($user->isRole(UserRole::Owner, UserRole::Cashier)) {
            return;
        }

        if ($user->isRole(UserRole::Customer) && $order->customer_id === $user->id) {
            return;
        }

        if (
            $user->isRole(UserRole::Driver)
            && $order->delivery()->where('driver_id', $user->id)->exists()
        ) {
            return;
        }

        abort(403, 'Anda tidak memiliki akses ke pesanan ini.');
    }

    private function resolveAddress(User $customer, array $data): ?Address
    {
        if ($data['delivery_method'] === DeliveryMethod::Pickup->value) {
            return null;
        }

        $address = $customer->addresses()
            ->whereKey($data['address_id'])
            ->first();

        if ($address === null) {
            throw ValidationException::withMessages([
                'address_id' => ['Alamat tidak ditemukan atau bukan milik customer.'],
            ]);
        }

        return $address;
    }

    /**
     * @param array<int, array{product_id: int, quantity: int}> $items
     * @return Collection<int, int>
     */
    private function normalizeItems(array $items): Collection
    {
        return collect($items)
            ->groupBy(fn (array $item): int => (int) $item['product_id'])
            ->map(fn (Collection $group): int => (int) $group->sum('quantity'));
    }

    /**
     * @param Collection<int, int> $items
     * @param Collection<int, Product> $products
     * @return array{subtotal: int}
     */
    private function calculateAndValidateItems(Collection $items, Collection $products): array
    {
        if ($products->count() !== $items->count()) {
            throw ValidationException::withMessages([
                'items' => ['Salah satu produk tidak ditemukan.'],
            ]);
        }

        $subtotal = 0;

        foreach ($items as $productId => $quantity) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            if ($product === null || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'items' => ["Produk ID {$productId} tidak aktif atau tidak ditemukan."],
                ]);
            }

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'items' => ['Jumlah produk harus lebih dari 0.'],
                ]);
            }

            if ($product->stock < $quantity) {
                throw ValidationException::withMessages([
                    'items' => [
                        "Stok {$product->name} tidak cukup. Tersedia {$product->stock}.",
                    ],
                ]);
            }

            $subtotal += $product->selling_price * $quantity;
        }

        return ['subtotal' => $subtotal];
    }

    /**
     * @param Collection<int, int> $normalizedItems
     * @param Collection<int, Product> $lockedProducts
     */
    private function persistItemsAndReserveStock(
        Order $order,
        User $actor,
        Collection $normalizedItems,
        Collection $lockedProducts,
        StockMovementType $movementType,
    ): void {
        foreach ($normalizedItems as $productId => $quantity) {
            /** @var Product|null $product */
            $product = $lockedProducts->get($productId);

            if ($product === null) {
                throw new RuntimeException("Produk ID {$productId} hilang saat transaksi.");
            }

            $itemSubtotal = $product->selling_price * $quantity;

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'price' => $product->selling_price,
                'quantity' => $quantity,
                'subtotal' => $itemSubtotal,
            ]);

            $stockBefore = $product->stock;
            $product->stock -= $quantity;
            $this->products->save($product);

            $this->stockMovements->create([
                'product_id' => $product->id,
                'user_id' => $actor->id,
                'type' => $movementType,
                'quantity' => -$quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $product->stock,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'notes' => $movementType === StockMovementType::Reservation
                    ? 'Reservasi stok untuk pesanan online.'
                    : 'Pengurangan stok transaksi kasir.',
            ]);
        }
    }

    private function generateOrderNumber(string $prefix = 'ORD'): string
    {
        do {
            $number = sprintf(
                '%s-%s-%06d',
                $prefix,
                now()->format('Ymd'),
                random_int(1, 999999),
            );
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
