<?php

namespace App\Models;

use App\Domain\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
