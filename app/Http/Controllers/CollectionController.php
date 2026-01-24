<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class CollectionController extends Controller
{
    public function index()
    {
        return Auth::user()->collections()->with('products')->get();
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $collection = Auth::user()->collections()->create([
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->is_public ?? false,
        ]);

        return response()->json($collection, 201);
    }

    public function update(Request $request, Collection $collection)
    {
        if ($collection->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collection->update($request->only(['name', 'description', 'is_public']));
        return response()->json($collection);
    }

    public function destroy(Collection $collection)
    {
        if ($collection->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collection->delete();
        return response()->json(['message' => 'Collection deleted']);
    }

    public function addProduct(Request $request, Collection $collection)
    {
        if ($collection->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate(['product_id' => 'required|exists:products,id']);
        
        $collection->products()->syncWithoutDetaching([$request->product_id]);
        return response()->json(['message' => 'Product added to collection']);
    }

    public function removeProduct(Collection $collection, Product $product)
    {
        if ($collection->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collection->products()->detach($product->id);
        return response()->json(['message' => 'Product removed from collection']);
    }
}
