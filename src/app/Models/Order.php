<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'cashier_id',
        'channel',
        'order_status',
        'payment_status',
        'delivery_method',
        'payment_method',
        'subtotal',
        'shipping_distance_km',
        'shipping_cost',
        'discount',
        'grand_total',
        'payment_amount',
        'change_amount',
        'address_snapshot',
        'notes',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => OrderChannel::class,
            'order_status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'payment_method' => PaymentMethod::class,
            'subtotal' => 'integer',
            'shipping_distance_km' => 'float',
            'shipping_cost' => 'integer',
            'discount' => 'integer',
            'grand_total' => 'integer',
            'payment_amount' => 'integer',
            'change_amount' => 'integer',
            'address_snapshot' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isRole(UserRole::Customer)) {
            return $query->where('customer_id', $user->id);
        }

        if ($user->isRole(UserRole::Driver)) {
            return $query->whereHas(
                'delivery',
                fn (Builder $deliveryQuery) => $deliveryQuery->where('driver_id', $user->id),
            );
        }

        return $query;
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                $filters['order_status'] ?? null,
                fn (Builder $builder, string $status) => $builder->where('order_status', $status),
            )
            ->when(
                $filters['payment_status'] ?? null,
                fn (Builder $builder, string $status) => $builder->where('payment_status', $status),
            )
            ->when(
                $filters['channel'] ?? null,
                fn (Builder $builder, string $channel) => $builder->where('channel', $channel),
            )
            ->when(
                $filters['search'] ?? null,
                fn (Builder $builder, string $search) => $builder->where(
                    'order_number',
                    'like',
                    "%{$search}%",
                ),
            );
    }

    public static function createOnline(User $customer, array $data): self
    {
        if (! $customer->isRole(UserRole::Customer)) {
            throw ValidationException::withMessages([
                'user' => ['Hanya customer yang dapat membuat pesanan online.'],
            ]);
        }

        return DB::transaction(function () use ($customer, $data): self {
            $address = static::resolveAddress($customer, $data);
            $items = static::normalizeItems($data['items']);
            $products = static::lockProducts($items->keys()->all());
            $subtotal = static::calculateAndValidateItems($items, $products);
            $shipping = $address === null
                ? ['distance_km' => null, 'cost' => 0]
                : static::calculateShipping($address, $data);

            $order = static::query()->create([
                'order_number' => static::generateOrderNumber(),
                'customer_id' => $customer->id,
                'cashier_id' => null,
                'channel' => OrderChannel::Online,
                'order_status' => OrderStatus::PendingPayment,
                'payment_status' => PaymentStatus::Unpaid,
                'delivery_method' => $data['delivery_method'],
                'payment_method' => PaymentMethod::Midtrans,
                'subtotal' => $subtotal,
                'shipping_distance_km' => $shipping['distance_km'],
                'shipping_cost' => $shipping['cost'],
                'discount' => 0,
                'grand_total' => $subtotal + $shipping['cost'],
                'address_snapshot' => $address?->toSnapshot(),
                'notes' => $data['notes'] ?? null,
            ]);

            static::persistItemsAndStock(
                order: $order,
                actor: $customer,
                items: $items,
                products: $products,
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

    public static function createCashierTransaction(User $cashier, array $data): self
    {
        if (! $cashier->isRole(UserRole::Cashier, UserRole::Owner)) {
            abort(403, 'Hanya cashier atau owner yang dapat membuat transaksi kasir.');
        }

        return DB::transaction(function () use ($cashier, $data): self {
            $items = static::normalizeItems($data['items']);
            $products = static::lockProducts($items->keys()->all());
            $subtotal = static::calculateAndValidateItems($items, $products);
            $paymentAmount = (int) $data['payment_amount'];

            if ($paymentAmount < $subtotal) {
                throw ValidationException::withMessages([
                    'payment_amount' => ['Nominal pembayaran kurang dari total transaksi.'],
                ]);
            }

            $order = static::query()->create([
                'order_number' => static::generateOrderNumber('POS'),
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => $cashier->id,
                'channel' => OrderChannel::Cashier,
                'order_status' => OrderStatus::Confirmed,
                'payment_status' => PaymentStatus::Paid,
                'delivery_method' => DeliveryMethod::Pickup,
                'payment_method' => PaymentMethod::Cash,
                'subtotal' => $subtotal,
                'shipping_distance_km' => null,
                'shipping_cost' => 0,
                'discount' => 0,
                'grand_total' => $subtotal,
                'payment_amount' => $paymentAmount,
                'change_amount' => $paymentAmount - $subtotal,
                'address_snapshot' => null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
            ]);

            static::persistItemsAndStock(
                order: $order,
                actor: $cashier,
                items: $items,
                products: $products,
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

    public function ensureVisibleTo(User $user): void
    {
        if ($user->isRole(UserRole::Owner, UserRole::Cashier)) {
            return;
        }

        if ($user->isRole(UserRole::Customer) && $this->customer_id === $user->id) {
            return;
        }

        if (
            $user->isRole(UserRole::Driver)
            && $this->delivery()->where('driver_id', $user->id)->exists()
        ) {
            return;
        }

        abort(403, 'Anda tidak memiliki akses ke pesanan ini.');
    }

    public function cancelBy(User $actor): self
    {
        $this->ensureVisibleTo($actor);

        if ($this->isPaid()) {
            throw ValidationException::withMessages([
                'order' => ['Pesanan yang sudah dibayar tidak dapat dibatalkan melalui endpoint ini.'],
            ]);
        }

        if ($this->order_status === OrderStatus::Cancelled) {
            return $this->load(['items', 'latestPayment']);
        }

        return DB::transaction(function () use ($actor): self {
            $this->restoreReservedStock(
                userId: $actor->id,
                notes: 'Pengembalian stok karena pesanan dibatalkan.',
            );

            $this->update([
                'order_status' => OrderStatus::Cancelled,
                'payment_status' => PaymentStatus::Cancelled,
            ]);

            return $this->refresh()->load([
                'customer',
                'cashier',
                'items.product',
                'latestPayment',
                'delivery.driver',
            ]);
        }, 3);
    }

    public function restoreReservedStock(
        ?int $userId = null,
        string $notes = 'Pengembalian stok pesanan.',
    ): bool {
        return DB::transaction(function () use ($userId, $notes): bool {
            /** @var self $order */
            $order = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            $movementQuery = StockMovement::query()
                ->where('reference_type', self::class)
                ->where('reference_id', $order->id);

            if (
                (clone $movementQuery)->where('type', StockMovementType::Restoration->value)->exists()
                || ! (clone $movementQuery)->where('type', StockMovementType::Reservation->value)->exists()
            ) {
                return false;
            }

            $order->loadMissing('items');
            $productIds = $order->items
                ->pluck('product_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();
            $products = static::lockProducts($productIds);

            foreach ($order->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                /** @var Product|null $product */
                $product = $products->get($item->product_id);

                if ($product === null) {
                    continue;
                }

                $stockBefore = $product->stock;
                $product->stock += $item->quantity;
                $product->save();

                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'user_id' => $userId,
                    'type' => StockMovementType::Restoration,
                    'quantity' => $item->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->stock,
                    'reference_type' => self::class,
                    'reference_id' => $order->id,
                    'notes' => $notes,
                ]);
            }

            return true;
        }, 3);
    }

    public function transitionTo(User $actor, OrderStatus $targetStatus): self
    {
        if (! $actor->isRole(UserRole::Cashier, UserRole::Owner)) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah status pesanan.');
        }

        $currentStatus = $this->order_status;

        if ($currentStatus === $targetStatus) {
            return $this->load(['items', 'customer', 'delivery.driver']);
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

        if (! in_array($targetStatus, $allowedTransitions[$currentStatus->value] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => [
                    "Perubahan status dari {$currentStatus->value} ke {$targetStatus->value} tidak diizinkan.",
                ],
            ]);
        }

        if ($targetStatus === OrderStatus::Cancelled) {
            return $this->cancelBy($actor);
        }

        if (
            $targetStatus === OrderStatus::Delivered
            && $this->delivery_method === DeliveryMethod::Delivery
        ) {
            throw ValidationException::withMessages([
                'status' => ['Pesanan delivery harus diselesaikan melalui alur driver.'],
            ]);
        }

        $this->update(['order_status' => $targetStatus]);

        return $this->refresh()->load(['items', 'customer', 'delivery.driver']);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    private static function resolveAddress(User $customer, array $data): ?Address
    {
        if ($data['delivery_method'] === DeliveryMethod::Pickup->value) {
            return null;
        }

        $address = $customer->addresses()->whereKey($data['address_id'])->first();

        if ($address === null) {
            throw ValidationException::withMessages([
                'address_id' => ['Alamat tidak ditemukan atau bukan milik customer.'],
            ]);
        }

        return $address;
    }

    /**
     * @return array{distance_km: float, cost: int}
     */
    private static function calculateShipping(Address $address, array $data): array
    {
        if ($address->latitude === null || $address->longitude === null) {
            throw ValidationException::withMessages([
                'address_id' => [
                    'Alamat delivery harus memiliki titik koordinat. Perbarui lokasi alamat terlebih dahulu.',
                ],
            ]);
        }

        $routeDistance = (float) $data['distance_km'];
        $straightDistance = static::straightLineDistance(
            startLatitude: (float) config('business.store.latitude'),
            startLongitude: (float) config('business.store.longitude'),
            endLatitude: (float) $address->latitude,
            endLongitude: (float) $address->longitude,
        );
        $tolerance = (float) config('business.shipping.distance_tolerance_km', 0.1);

        if ($routeDistance + $tolerance < $straightDistance) {
            throw ValidationException::withMessages([
                'distance_km' => [
                    'Jarak pengiriman lebih pendek dari jarak minimum lokasi toko ke alamat.',
                ],
            ]);
        }

        $ratePerKm = (int) config('business.shipping.rate_per_km', 5000);

        return [
            'distance_km' => round($routeDistance, 3),
            'cost' => (int) round($routeDistance * $ratePerKm),
        ];
    }

    private static function straightLineDistance(
        float $startLatitude,
        float $startLongitude,
        float $endLatitude,
        float $endLongitude,
    ): float {
        $earthRadiusKm = 6371.0088;
        $latitudeDelta = deg2rad($endLatitude - $startLatitude);
        $longitudeDelta = deg2rad($endLongitude - $startLongitude);
        $startLatitudeRadians = deg2rad($startLatitude);
        $endLatitudeRadians = deg2rad($endLatitude);

        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($startLatitudeRadians)
            * cos($endLatitudeRadians)
            * sin($longitudeDelta / 2) ** 2;

        return 2 * $earthRadiusKm * asin(min(1, sqrt($haversine)));
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @return Collection<int, int>
     */
    private static function normalizeItems(array $items): Collection
    {
        return collect($items)
            ->groupBy(fn (array $item): int => (int) $item['product_id'])
            ->map(fn (Collection $group): int => (int) $group->sum('quantity'));
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    private static function lockProducts(array $ids): Collection
    {
        return Product::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, int>  $items
     * @param  Collection<int, Product>  $products
     */
    private static function calculateAndValidateItems(Collection $items, Collection $products): int
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
                    'items' => ["Stok {$product->name} tidak cukup. Tersedia {$product->stock}."],
                ]);
            }

            $subtotal += $product->selling_price * $quantity;
        }

        return $subtotal;
    }

    /**
     * @param  Collection<int, int>  $items
     * @param  Collection<int, Product>  $products
     */
    private static function persistItemsAndStock(
        self $order,
        User $actor,
        Collection $items,
        Collection $products,
        StockMovementType $movementType,
    ): void {
        foreach ($items as $productId => $quantity) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            if ($product === null) {
                throw new RuntimeException("Produk ID {$productId} hilang saat transaksi.");
            }

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'price' => $product->selling_price,
                'quantity' => $quantity,
                'subtotal' => $product->selling_price * $quantity,
            ]);

            $stockBefore = $product->stock;
            $product->stock -= $quantity;
            $product->save();

            StockMovement::query()->create([
                'product_id' => $product->id,
                'user_id' => $actor->id,
                'type' => $movementType,
                'quantity' => -$quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $product->stock,
                'reference_type' => self::class,
                'reference_id' => $order->id,
                'notes' => $movementType === StockMovementType::Reservation
                    ? 'Reservasi stok untuk pesanan online.'
                    : 'Pengurangan stok transaksi kasir.',
            ]);
        }
    }

    private static function generateOrderNumber(string $prefix = 'ORD'): string
    {
        do {
            $number = sprintf(
                '%s-%s-%06d',
                $prefix,
                now()->format('Ymd'),
                random_int(1, 999999),
            );
        } while (static::query()->where('order_number', $number)->exists());

        return $number;
    }
}
