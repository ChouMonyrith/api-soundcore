<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'rating' => (float) $this->rating,
            'download_count' => $this->download_count,
            'category_id' => $this->category_id,
            'producer_profile_id' => $this->producer_profile_id,
            'category' => $this->category->name,
            'bpm' => $this->bpm,
            'key' => $this->key,
            'file_path' => $this->file_path ? url('api/storage/' . $this->file_path) : null,
            'tags' => $this->tags,
            'image_path' => $this->image_path
                ? asset('storage/' . ltrim($this->image_path, '/'))
                : null,
            'artist' => [
                'id' => $this->producer->id,
                'name' => $this->producer->display_name,
                'avatar' => $this->producer->avatar_path,
            ],
            'created_at' => $this->created_at->diffForHumans(),
            'has_purchased' => auth('sanctum')->check() ? auth('sanctum')->user()->hasPurchased($this->id) : false,
            'is_liked' => $this->is_liked,
            'reviews' => $this->whenLoaded('reviews', function () {
                return $this->reviews->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at->diffForHumans(),
                        'user' => [
                            'id' => $review->user->id,
                            'name' => $review->user->name,
                            'avatar' => $review->user->avatar_path,
                        ],
                    ];
                });
            }),
        ];
    }
}
