<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    protected $fillable = ['user_id', 'product_id', 'order_id', 'ip_address', 'downloaded_at'];
    public $timestamps = false; // We use downloaded_at manually or via DB default
    protected $dates = ['downloaded_at'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function order() {
        return $this->belongsTo(Order::class);
    }   
}
