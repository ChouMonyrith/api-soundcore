<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'producer_profile_id', 'category_id', 'name', 'slug', 'description', 
        'price', 'rating', 'download_count', 'image_path', 
        'audio_preview_path', 'file_path','bpm','key','tags',
    ];

    protected $casts = [
        'tags' => 'array',
        'rating' => 'float',
    ];

    public function producer() {
        return $this->belongsTo(ProducerProfile::class, 'producer_profile_id');
    }

    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function reviews() {
        return $this->hasMany(Review::class);
    }

    public function downloads() {
        return $this->hasMany(Download::class);
    }

    // Helper to calculate rating
    public function updateRating() {
        $this->rating = $this->reviews()->avg('rating') ?? 0;
        $this->save();
    }

    public function likes() {
        return $this->belongsToMany(User::class, 'likes', 'product_id', 'user_id')->withTimestamps();
    }

    public function collections() {
        return $this->belongsToMany(Collection::class, 'collection_items', 'product_id', 'collection_id')->withTimestamps();
    }

    public function getIsLikedAttribute() {
        if (auth('sanctum')->check()) {
            return $this->likes()->where('user_id', auth('sanctum')->id())->exists();
        }
        return false;
    }
}
