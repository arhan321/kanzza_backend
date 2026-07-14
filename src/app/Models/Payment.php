<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'attempt_number',
        'provider',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'snap_token',
        'snap_redirect_url',
        'payment_type',
        'gross_amount',
        'status',
        'fraud_status',
        'transaction_time',
        'settlement_time',
        'expiry_time',
        'paid_at',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'status' => PaymentStatus::class,
            'transaction_time' => 'datetime',
            'settlement_time' => 'datetime',
            'expiry_time' => 'datetime',
            'paid_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isReusable(): bool
    {
        return $this->status === PaymentStatus::Pending
            && $this->snap_redirect_url !== null
            && ($this->expiry_time === null || $this->expiry_time->isFuture());
    }
}
