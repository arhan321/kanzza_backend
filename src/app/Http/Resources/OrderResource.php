<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer' => new UserResource($this->whenLoaded('customer')),
            'cashier' => new UserResource($this->whenLoaded('cashier')),
            'channel' => $this->enumValue($this->channel),
            'order_status' => $this->enumValue($this->order_status),
            'payment_status' => $this->enumValue($this->payment_status),
            'delivery_method' => $this->enumValue($this->delivery_method),
            'payment_method' => $this->enumValue($this->payment_method),
            'subtotal' => $this->subtotal,
            'shipping_distance_km' => $this->shipping_distance_km,
            'shipping_cost' => $this->shipping_cost,
            'discount' => $this->discount,
            'grand_total' => $this->grand_total,
            'payment_amount' => $this->payment_amount,
            'change_amount' => $this->change_amount,
            'address' => $this->address_snapshot,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at?->toISOString(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'latest_payment' => new PaymentResource($this->whenLoaded('latestPayment')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'delivery' => new DeliveryResource($this->whenLoaded('delivery')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
