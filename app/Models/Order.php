<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'transaction_id', 'user_id', 'subtotal', 'tax', 'total', 'status', 
        'payment_method', 'billing_name', 'billing_email',
        'md5', 'payment_metadata', 'paid_at'
    ];

    protected $casts = [
        'payment_metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function orderItems() {
        return $this->hasMany(OrderItem::class);
    }
}
