<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'is_public', 'cover_image'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'collection_items', 'collection_id', 'product_id')->withTimestamps();
    }
}
