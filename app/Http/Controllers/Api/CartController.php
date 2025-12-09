<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\Product;
use App\Http\Resources\CartResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $carts = $request->user()->carts()->with('product')->get();
        return CartResource::collection($carts);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'license_type' => 'in:standard,extended',
            'quantity' => 'integer|min:1'
        ]);

        $user = $request->user();
        $productId = $request->product_id;
        $licenseType = $request->input('license_type', 'standard');
        $quantity = $request->input('quantity', 1);

        // Check if item already exists in cart
        $cartItem = $user->carts()
                        ->where('product_id', $productId)
                        ->where('license_type', $licenseType)
                        ->first();

        if ($cartItem) {
            $cartItem->increment('quantity', $quantity);
        } else {
            $cartItem = $user->carts()->create([
                'product_id' => $productId,
                'license_type' => $licenseType,
                'quantity' => $quantity
            ]);
        }

        return response()->json([
            'message' => 'Added to cart',
            'cart' => new CartResource($cartItem->load('product'))
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'license_type' => 'in:standard,extended'
        ]);

        $user = $request->user();
        $cartItem = $user->carts()->findOrFail($id);

        $cartItem->update($request->only(['quantity', 'license_type']));

        return new CartResource($cartItem->load('product'));
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $cartItem = $user->carts()->findOrFail($id);
        $cartItem->delete();

        return response()->json(['message' => 'Item removed from cart']);
    }

    public function clear(Request $request)
    {
        $request->user()->carts()->delete();
        return response()->json(['message' => 'Cart cleared']);
    }
}
