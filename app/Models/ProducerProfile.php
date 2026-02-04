<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProducerProfile extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'display_name', 'bio', 'avatar_path', 'sales_count', 'status','cover_path','social_links','location','website'];

    protected $casts = [
        'social_links' => 'array',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function products() {
        return $this->hasMany(Product::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'producer_id', 'follower_id');
    }

    public function getIsFollowedAttribute() {
        if (!auth()->check()) return false;
        return $this->followers()->where('follower_id', auth()->id())->exists();
    }
}
