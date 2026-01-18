<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PublicProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->display_name,
            'handle' => '@' . str_replace(' ', '', strtolower($this->display_name)), // Generate handle dynamic
            'role' => 'Pro Sound Designer', // Or add this field to DB
            'location' => $this->location,
            'website' => $this->website,
            'join_date' => $this->created_at->format('F Y'),
            'bio' => $this->bio,
            
            // Images
            'avatar' => $this->avatar_path ? Storage::url($this->avatar_path) : null,
            'cover_image' => $this->cover_image_path ? Storage::url($this->cover_image_path) : null,
            
            // Stats
            'stats' => [
                'sounds' => $this->products_count,
                'followers' => $this->followers_count,
                'following' => 0, // Producers don't follow others in this schema yet, or map to User following
                'sales' => $this->sales_count,
            ],
            
            
            'is_following' => $this->is_followed,
            'socials' => $this->social_links,
        ];
    }
}