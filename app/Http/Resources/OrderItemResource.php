<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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
            'product_id' => $this->product_id, // Was sound_id
            'product' => [
                'id' => $this->product->id ?? null,
                'name' => $this->product->name ?? 'Unknown Product',
                'slug' => $this->product->slug ?? '',
                'preview_path' => $this->product->preview_path ?? '',
                'image_path' => $this->product->image_path ? url('api/storage/' . $this->product->image_path) : null,
            ],
            'price' => $this->price,
            'license_type' => $this->license_type,
            'quantity' => $this->quantity,
        ];
    }
}
