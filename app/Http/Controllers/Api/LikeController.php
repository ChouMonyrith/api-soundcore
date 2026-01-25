<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class LikeController extends Controller
{
    public function toggleLike(Product $product)
    {
        $user = Auth::user();

        if ($user->likes()->where('product_id', $product->id)->exists()) {
            $user->likes()->detach($product->id);
            return response()->json(['message' => 'Product unliked', 'liked' => false]);
        } else {
            $user->likes()->attach($product->id);
            return response()->json(['message' => 'Product liked', 'liked' => true]);
        }
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $likes = $user->likes()->with(['category', 'producer.user'])->paginate(12);
        
        return \App\Http\Resources\ProductResource::collection($likes);
    }
}
