<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'event_type',
        'event_id',
        'order_id',
        'payload',
        'signature',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Check if this webhook was already processed
     */
    public static function isProcessed(string $eventId): bool
    {
        return self::where('event_id', $eventId)
            ->where('status', 'processed')
            ->exists();
    }
    
}
