<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductResource;

class CollectionResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'is_public' => (bool) $this->is_public,
            'cover_image' => $this->cover_image ? url('storage/' . $this->cover_image) : null,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user'), // Or a UserResource if you have one
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'products_count' => $this->whenCounted('products'),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
