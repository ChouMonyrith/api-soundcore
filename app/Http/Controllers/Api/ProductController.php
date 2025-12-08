<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $query = Product::with(['category', 'producer']);

        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('slug', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('tags', 'LIKE', "%{$searchTerm}%");
            });
        }

        return ProductResource::collection($query->paginate(12));
    }

    public function store(Request $request){
        if(!auth()->user()->can('upload products')){
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        Log::info("Product created: ".json_encode($request->all()));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'description' => 'required|string',
            'audio_file' => 'required|file|mimes:mp3,wav,zip|max:50000', // 50MB
            'image_path' => 'nullable|image|max:2048',
            'bpm' => 'nullable|numeric',
            'key' => 'nullable|string',
            'tags' => 'nullable|string',
        ]);
        
        

        $filePath = $request->file('audio_file')->store('products/files', 'private'); // Private bucket
        $imagePath = $request->file('image_path') 
            ? $request->file('image_path')->store('products/covers', 'public') 
            : null;

        $producerProfile = auth()->user()->producerProfile ?? auth()->user()->producerProfile()->create(['display_name' => auth()->user()->name]);

        $product = Product::create([
            'producer_profile_id' => $producerProfile->id,
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . uniqid(),
            'description' => $validated['description'],
            'price' => $validated['price'],
            'file_path' => $filePath,
            'image_path' => $imagePath,
            'bpm' => $validated['bpm'],
            'key' => $validated['key'],
            'tags' => $validated['tags'],
        ]);


        return response()->json([
            'message' => 'Product created successfully',
            'product' => new ProductResource($product),
        ], 201);
    }

    public function show(string $slug)
    {

        $product = Product::where('slug', $slug)->firstOrFail();
        $product->load(['category', 'producer.user', 'reviews.user']);

        return new ProductResource($product);
    }

    public function update(Request $request, Product $product)
    {
        $product->load('producer');
        $this->authorize('update', $product);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'bpm' => 'sometimes|numeric',
            'key' => 'sometimes|string',
            'image_path' => 'sometimes|image|max:2048',
            'tags' => 'sometimes|string',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']).'-'.uniqid();
        }
        
        Log::info('Product update request data:', $request->all());
        Log::info('Product update validated data:', $validated);

        $product->update($validated);

        if ($request->hasFile('image_path')) {
            $imagePath = $request->file('image_path')->store('products/covers', 'public');
            $product->update(['image_path' => $imagePath]);
        }

        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    public function serveFile($path)
    {
        if (Storage::disk('private')->exists($path)) {
            return response()->file(Storage::disk('private')->path($path));
        }

        if (Storage::disk('public')->exists($path)) {
            return response()->file(Storage::disk('public')->path($path));
        }

        abort(404);
    }
}
