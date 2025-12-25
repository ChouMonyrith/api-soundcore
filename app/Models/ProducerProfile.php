<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProducerProfile extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'display_name', 'bio', 'avatar_path', 'sales_count', 'status'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function products() {
        return $this->hasMany(Product::class);
    }
}
