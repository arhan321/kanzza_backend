<?php

namespace App\Models;

use App\Domain\Enums\DeliveryMethod;
use App\Domain\Enums\OrderChannel;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentMethod;
use App\Domain\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }
}
