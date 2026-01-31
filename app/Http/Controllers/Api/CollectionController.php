<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\CollectionResource;
use App\Http\Resources\ProductResource;

class CollectionController extends Controller
{
    public function index()
    {
        $collections = Auth::user()->collections()->with('products')->get();
        return CollectionResource::collection($collections);
    }

    public function show(Collection $collection)
    {
        // Allow if public or owned by user
        $user = auth('sanctum')->user();

        if (Gate::forUser($user)->denies('view', $collection)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return new CollectionResource($collection->load(['products', 'user']));
    }

    public function sounds(Collection $collection)
    {
        // Allow if public or owned by user
        $user = auth('sanctum')->user();
        if (Gate::forUser($user)->denies('view', $collection)) {
             return response()->json(['message' => 'Unauthorized'], 403);
        }

        return ProductResource::collection($collection->products);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Collection::class);
       
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'required|boolean',
            'cover_image' => 'nullable|image|max:10000'
        ]);

        $coverImagePath = null;
        if ($request->hasFile('cover_image')) {
            $coverImagePath = $request->file('cover_image')->store('collections', 'public');
        }

        $collection = Auth::user()->collections()->create([
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->is_public ?? false,
            'cover_image' => $coverImagePath,
        ]);

        return new CollectionResource($collection->load('user'));
    }

    public function update(Request $request, Collection $collection)
    {
        $this->authorize('update', $collection);

        $data = $request->only(['name', 'description', 'is_public']);

        if ($request->hasFile('cover_image')) {
            // Delete old image
            if ($collection->cover_image) {
                Storage::disk('public')->delete($collection->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('collections', 'public');
        }

        $collection->update($data);
        return new CollectionResource($collection);
    }

    public function destroy(Collection $collection)
    {
        $this->authorize('delete', $collection);

        $collection->delete();
        return response()->json(['message' => 'Collection deleted']);
    }

    public function addProduct(Request $request, Collection $collection)
    {
        $this->authorize('addProduct', $collection);

        $request->validate(['product_id' => 'required|exists:products,id']);
        
        $collection->products()->syncWithoutDetaching([$request->product_id]);
        return response()->json(['message' => 'Product added to collection']);
    }

    public function removeProduct(Collection $collection, Product $product)
    {
        $this->authorize('removeProduct', $collection);

        $collection->products()->detach($product->id);
        return response()->json(['message' => 'Product removed from collection']);
    }
}
