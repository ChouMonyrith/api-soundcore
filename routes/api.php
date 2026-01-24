<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProducerController;
use App\Http\Controllers\Api\ProducerRequestController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\LikeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;







Route::get('products', [ProductController::class, 'index']);
Route::get('products/popular', [ProductController::class, 'popularProduct']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('products/{product}/related', [ProductController::class, 'relatedProduct']);

Route::get('producers/top-producers', [ProducerController::class, 'topProducer']);
Route::get('producers/{id}', [ProducerController::class, 'show']);
Route::get('producers/{id}/sounds', [ProducerController::class, 'sounds']);

Route::get('tags/trending', [ProductController::class, 'trendingTags']);

Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{id}', [CategoryController::class, 'show']);
Route::get('storage/{path}', [ProductController::class, 'serveFile'])->where('path', '.*');

Route::get('/profiles/{id}', [ProfileController::class, 'show'])->whereNumber('id');
Route::get('/profiles/{id}/sounds', [ProfileController::class, 'sounds'])->whereNumber('id');

Route::middleware(['auth:sanctum'])->group(function (){
    
    Route::get('user', function (Request $request) {
        return $request->user()->load(['roles', 'producerProfile']);
    });
    
    Route::get('my-downloads', [DownloadController::class, 'index']);

    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('{product}', [ProductController::class, 'update']);
        Route::delete('{product}', [ProductController::class, 'destroy']);
        Route::post('{product}/reviews', [ProductController::class, 'storeReview']);
    });

    Route::prefix('categories')->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('{id}', [CategoryController::class, 'update']);
        Route::delete('{id}', [CategoryController::class, 'destroy']);
        // Route::get('{id}', [CategoryController::class, 'show']);
    });


    // Producer Request Routes
    Route::prefix('producer/request')->group(function () {
        Route::post('/', [ProducerRequestController::class, 'store']); 
        
        Route::middleware(['role:admin'])->group(function () {
            Route::get('/', [ProducerRequestController::class, 'index']);
            Route::post('{id}/approve', [ProducerRequestController::class, 'approve']);
            Route::post('{id}/reject', [ProducerRequestController::class, 'reject']);
        });
    });

    // Dashboard
    Route::get('dashboard/stats', [\App\Http\Controllers\Api\DashboardController::class, 'stats']);
    Route::get('dashboard/recent-sales', [\App\Http\Controllers\Api\DashboardController::class, 'recentSales']);


    // Orders
    Route::get('orders/download/{product}', [OrderController::class, 'download'])->withTrashed();
    Route::post('orders/check-status', [OrderController::class, 'checkStatus']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show']);

    // Cart
    Route::delete('carts', [CartController::class, 'clear']);
    Route::apiResource('carts', CartController::class);

    //Profile
    Route::get('/profiles/me', [ProfileController::class, 'me']);
    Route::post('/profiles/{id}/follow', [ProfileController::class, 'toggleFollow']);

    // Likes
    Route::post('/products/{product}/like', [LikeController::class, 'toggleLike']);
    Route::get('/me/likes', [LikeController::class, 'index']);

    // Collections
    Route::apiResource('collections', CollectionController::class);
    Route::post('/collections/{collection}/products', [CollectionController::class, 'addProduct']);
    Route::delete('/collections/{collection}/products/{product}', [CollectionController::class, 'removeProduct']);
});