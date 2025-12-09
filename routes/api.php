<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProducerController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('producers/{id}', [ProducerController::class, 'show']);
Route::get('producers/{id}/sounds', [ProducerController::class, 'sounds']);

Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{id}', [CategoryController::class, 'show']);
Route::get('storage/{path}', [ProductController::class, 'serveFile'])->where('path', '.*');

Route::middleware(['auth:sanctum'])->group(function (){
    
    Route::get('user', function (Request $request) {
        return $request->user()->load(['roles', 'producerProfile']);
    });
    
    Route::get('my-downloads', [DownloadController::class, 'index']);

    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('{product}', [ProductController::class, 'update']);
        Route::delete('{product}', [ProductController::class, 'destroy']);
    });

    Route::prefix('categories')->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('{id}', [CategoryController::class, 'update']);
        Route::delete('{id}', [CategoryController::class, 'destroy']);
        // Route::get('{id}', [CategoryController::class, 'show']);
    });

    // Producer Request Routes
    // Producer Request Routes
    Route::prefix('producer/request')->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\ProducerRequestController::class, 'store']); // User can request
        
        Route::middleware(['role:admin'])->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\ProducerRequestController::class, 'index']);
            Route::post('{id}/approve', [\App\Http\Controllers\Api\ProducerRequestController::class, 'approve']);
            Route::post('{id}/reject', [\App\Http\Controllers\Api\ProducerRequestController::class, 'reject']);
        });
    });

    // Orders
    Route::get('orders/download/{product}', [OrderController::class, 'download']);
    Route::post('orders/check-status', [OrderController::class, 'checkStatus']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show']);

    // Cart
    Route::delete('carts', [CartController::class, 'clear']);
    Route::apiResource('carts', CartController::class);
});