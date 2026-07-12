<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'attempt_number' => $this->attempt_number,
            'provider' => $this->provider,
            'midtrans_order_id' => $this->midtrans_order_id,
            'midtrans_transaction_id' => $this->midtrans_transaction_id,
            'snap_token' => $this->snap_token,
            'redirect_url' => $this->snap_redirect_url,
            'payment_type' => $this->payment_type,
            'gross_amount' => $this->gross_amount,
            'status' => $this->status instanceof BackedEnum ? $this->status->value : $this->status,
            'fraud_status' => $this->fraud_status,
            'transaction_time' => $this->transaction_time?->toISOString(),
            'settlement_time' => $this->settlement_time?->toISOString(),
            'expiry_time' => $this->expiry_time?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
