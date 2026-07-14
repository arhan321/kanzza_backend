<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'driver_id',
        'assigned_by',
        'status',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'cod_payment_received_at',
        'cod_payment_received_by',
        'proof_image',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cod_payment_received_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function codPaymentReceiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cod_payment_received_by');
    }

    public function scopeForDriver(Builder $query, User $driver): Builder
    {
        return $query->where('driver_id', $driver->id);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query->when(
            $filters['status'] ?? null,
            fn (Builder $builder, string $status) => $builder->where('status', $status),
        );
    }

    public static function assignDriver(User $actor, Order $order, User $driver): self
    {
        if (! $actor->isRole(UserRole::Owner, UserRole::Cashier)) {
            abort(403, 'Anda tidak memiliki akses untuk menugaskan driver.');
        }

        if (! $driver->isRole(UserRole::Driver) || ! $driver->isActive()) {
            throw ValidationException::withMessages([
                'driver_id' => ['User yang dipilih bukan driver aktif.'],
            ]);
        }

        $isUnpaidCod = $order->channel === OrderChannel::Online
            && $order->payment_method === PaymentMethod::Cash
            && $order->payment_status === PaymentStatus::Unpaid;

        if ($order->payment_status !== PaymentStatus::Paid && ! $isUnpaidCod) {
            throw ValidationException::withMessages([
                'order' => [
                    'Pesanan harus sudah dibayar atau menggunakan pembayaran COD.',
                ],
            ]);
        }

        if ($order->delivery_method !== DeliveryMethod::Delivery) {
            throw ValidationException::withMessages([
                'order' => ['Pesanan pickup tidak memerlukan driver.'],
            ]);
        }

        if (! in_array($order->order_status, [OrderStatus::Ready, OrderStatus::Assigned], true)) {
            throw ValidationException::withMessages([
                'order' => ['Pesanan harus berstatus ready sebelum ditugaskan ke driver.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $order, $driver): self {
            $delivery = static::query()->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'driver_id' => $driver->id,
                    'assigned_by' => $actor->id,
                    'status' => DeliveryStatus::Assigned,
                    'assigned_at' => now(),
                ],
            );

            $order->update(['order_status' => OrderStatus::Assigned]);

            return $delivery->refresh()->load([
                'order.items',
                'order.customer',
                'driver',
            ]);
        });
    }

    public function ensureAssignedTo(User $driver): void
    {
        if ($this->driver_id !== $driver->id) {
            abort(403, 'Pengiriman ini tidak ditugaskan kepada Anda.');
        }
    }

    public function transitionTo(
        User $driver,
        DeliveryStatus $targetStatus,
        array $data,
    ): self {
        $this->ensureAssignedTo($driver);

        return DB::transaction(function () use ($driver, $targetStatus, $data): self {
            /** @var self $delivery */
            $delivery = static::query()
                ->whereKey($this->id)
                ->lockForUpdate()
                ->firstOrFail();
            $delivery->ensureAssignedTo($driver);

            /** @var Order $order */
            $order = Order::query()
                ->whereKey($delivery->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $allowedTransitions = [
                DeliveryStatus::Assigned->value => [DeliveryStatus::PickedUp],
                DeliveryStatus::PickedUp->value => [DeliveryStatus::OnDelivery],
                DeliveryStatus::OnDelivery->value => [DeliveryStatus::Delivered],
            ];

            if (! in_array(
                $targetStatus,
                $allowedTransitions[$delivery->status->value] ?? [],
                true,
            )) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Perubahan status dari {$delivery->status->value} ke {$targetStatus->value} tidak diizinkan.",
                    ],
                ]);
            }

            $deliveryData = [
                'status' => $targetStatus,
                'notes' => $data['notes'] ?? $delivery->notes,
            ];

            $orderStatus = match ($targetStatus) {
                DeliveryStatus::PickedUp => OrderStatus::PickedUp,
                DeliveryStatus::OnDelivery => OrderStatus::OnDelivery,
                DeliveryStatus::Delivered => OrderStatus::Delivered,
                default => OrderStatus::Assigned,
            };

            if ($targetStatus === DeliveryStatus::PickedUp) {
                $deliveryData['picked_up_at'] = now();
            }

            if ($targetStatus === DeliveryStatus::Delivered) {
                $deliveryData['delivered_at'] = now();
                $deliveryData['proof_image'] = $data['proof_image_path']
                    ?? $delivery->proof_image;

                if ($order->payment_method === PaymentMethod::Cash) {
                    if (($data['payment_received'] ?? false) !== true) {
                        throw ValidationException::withMessages([
                            'payment_received' => [
                                'Driver wajib mengonfirmasi pembayaran COD sudah diterima.',
                            ],
                        ]);
                    }

                    $paidAt = now();
                    $deliveryData['cod_payment_received_at'] = $paidAt;
                    $deliveryData['cod_payment_received_by'] = $driver->id;

                    $order->update([
                        'order_status' => OrderStatus::Delivered,
                        'payment_status' => PaymentStatus::Paid,
                        'payment_amount' => $order->grand_total,
                        'change_amount' => 0,
                        'paid_at' => $paidAt,
                    ]);

                    StockMovement::query()
                        ->where('reference_type', Order::class)
                        ->where('reference_id', $order->id)
                        ->where('type', StockMovementType::Reservation->value)
                        ->update([
                            'type' => StockMovementType::Sale->value,
                            'notes' => 'Penjualan COD dikonfirmasi oleh driver.',
                        ]);
                }
            }

            $delivery->update($deliveryData);

            if (! (
                $targetStatus === DeliveryStatus::Delivered
                && $order->payment_method === PaymentMethod::Cash
            )) {
                $order->update(['order_status' => $orderStatus]);
            }

            return $delivery->refresh()->load([
                'order.items',
                'order.customer',
                'driver',
                'codPaymentReceiver',
            ]);
        }, 3);
    }
}
