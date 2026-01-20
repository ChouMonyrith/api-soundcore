<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $expirationTime = now()->subMinutes(10);
    
    $deletedCount = Order::where('status', 'pending')
        ->where('payment_method', 'khqr')
        ->where('created_at', '<', $expirationTime)
        ->update(['status' => 'expired']);

    if ($deletedCount > 0) {
        Log::info("Cleaned up {$deletedCount} expired KHQR orders.");
    }
})->everyMinute();
