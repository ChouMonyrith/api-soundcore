<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
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
            'product_id' => $this->product_id,
            'product' => [
                'id' => $this->product->id ?? null,
                'name' => $this->product->name ?? 'Unknown Product',
                'slug' => $this->product->slug ?? '',
                'preview_path' => $this->product->preview_path ?? '',
                'price' => $this->product->price ?? 0,
                'image_path' => $this->product->image_path ? url('api/storage/' . $this->product->image_path) : null,
            ],
            'license_type' => $this->license_type,
            'price' => $this->calculatedPrice(),
            'quantity' => $this->quantity,
        ];
    }
    
    protected function calculatedPrice()
    {
        if (!$this->product) return 0;
        $multiplier = $this->license_type === 'extended' ? 1.5 : 1.0;
        return $this->product->price * $multiplier;
    }
}
