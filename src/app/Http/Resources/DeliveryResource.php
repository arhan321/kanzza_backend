<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'driver' => new UserResource($this->whenLoaded('driver')),
            'status' => $this->status instanceof BackedEnum ? $this->status->value : $this->status,
            'assigned_at' => $this->assigned_at?->toISOString(),
            'picked_up_at' => $this->picked_up_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'cod_payment_received' => $this->cod_payment_received_at !== null,
            'cod_payment_received_at' => $this->cod_payment_received_at?->toISOString(),
            'cod_payment_received_by' => new UserResource(
                $this->whenLoaded('codPaymentReceiver'),
            ),
            'proof_image' => $this->proof_image,
            'notes' => $this->notes,
            'order' => new OrderResource($this->whenLoaded('order')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
