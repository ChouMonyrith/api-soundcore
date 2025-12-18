<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'payment_status' => $this->status, // Alias for frontend compatibility if needed
            'total' => $this->total,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'billing_email' => $this->billing_email,
            'billing_name' => $this->billing_name,
            'payment_method' => $this->payment_method,
            'created_at' => $this->created_at,
            'items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
        ];
    }
}
