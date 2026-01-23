<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        $user = $request->user('sanctum');

        $query = Product::with(['category', 'producer']);

        if ($request->has('category')) {
            $query->whereHas('category', fn ($q) =>
                $q->where('slug', $request->category)
            );
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(fn ($q) =>
                $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('slug', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->orWhere('tags', 'LIKE', "%{$search}%")
                ->orWhereHas('producer', fn ($q) =>
                    $q->where('name', 'LIKE', "%{$search}%")
                )
            );
        }

        // if($request->has('popular')){
        //     $query->orderBy('download_count', 'desc')->take(5)->get();
        // }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('tags')) {
            $tags = array_filter(array_map('trim', explode(',', $request->tags)));

            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereRaw('LOWER(tags) LIKE ?', ['%' . strtolower($tag) . '%']);
                }
            });
        }

        if ($request->filled('sort')) {
            match ($request->sort) {
                'price_asc' => $query->orderBy('price'),
                'price_desc' => $query->orderByDesc('price'),
                'latest' => $query->orderByDesc('created_at'),
                'popular' => $query->orderByDesc('download_count'),
            };
        }

        $limit = $request->input('limit', 8);
        $products = $query->paginate($limit);

        if ($user) {
            $purchasedIds = $user->orders()
                ->where('status', 'paid')
                ->whereHas('orderItems')
                ->with('orderItems:order_id,product_id')
                ->get()
                ->pluck('orderItems.*.product_id')
                ->flatten()
                ->unique()
                ->toArray();

            $products->getCollection()->transform(function ($product) use ($purchasedIds) {
                $product->has_purchased = in_array($product->id, $purchasedIds);
                return $product;
            });
        }

        return ProductResource::collection($products);
    }


    public function store(Request $request){
        if(!auth()->user()->can('upload products')){
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        Log::info("Product created: ".json_encode($request->all()));

        if($request->hasFile('audio_file')){
            Log::info('Audio file mime: ' . $request->file('audio_file')->getMimeType());
            Log::info('Audio file client mime: ' . $request->file('audio_file')->getClientMimeType());
        }

        if($request->hasFile('image_path')){
            Log::info('Image file size: ' . $request->file('image_path')->getSize());
            Log::info('Image file mime: ' . $request->file('image_path')->getMimeType());
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:categories,id',
                'price' => 'required|numeric|min:0',
                'description' => 'required|string',
                // Allow broader mime types
                'audio_file' => 'required|file|mimetypes:audio/mpeg,audio/wav,audio/x-wav,application/octet-stream|max:1000000', 
                'image_path' => 'nullable|image|max:10240', // Increased to 10MB just in case
                'bpm' => 'nullable|numeric',
                'key' => 'nullable|string',
                'tags' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed: ' . json_encode($e->errors()));
            throw $e;
        }
        
        

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

    public function storeReview(Request $request, Product $product)
    {
        if (!auth()->user()->hasPurchased($product->id)) {
            return response()->json([
                'message' => 'You must purchase this product to leave a review.'
            ], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000',
        ]);

        $review = $product->reviews()->create([
            'user_id' => auth()->id(),
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        $product->updateRating();

        return response()->json([
            'message' => 'Review submitted successfully',
            'review' => $review
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

    public function trendingTags()
    {
        $items = OrderItem::with('product')
                ->whereHas('product')
                ->where('created_at', '>=', now()->subDays(30))
                ->get();

        $tagCounts = [];

        foreach ($items as $item) {
            $productTags = $item->product->tags;
            $tags = is_array($productTags) ? $productTags : explode(',', $productTags ?? '');

            foreach ($tags as $tag) {
                $tag = trim($tag);
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + $item->quantity;
            }
        }

        arsort($tagCounts);

        $trendingTags = array_slice(array_keys($tagCounts), 0, 6);

        return response()->json([
            'tags' => $trendingTags
        ]);
    }

    public function popularProduct()
    {
        $products = Product::with('category', 'producer.user', 'reviews.user')
            ->orderBy('download_count', 'desc')
            ->take(4)
            ->get();

        return ProductResource::collection($products);
    }

    public function relatedProduct(Request $request, string $slug)
    {
        $limit = $request->limit ?? 4;

        $product = Product::where('slug', $slug)->firstOrFail();

        $query = Product::query()
            ->where('id', '!=', $product->id);

        $query->where(function ($q) use ($product) {
            
            // 1. Tags
            if ($product->tags) {
                $tags = explode(',', $product->tags);
                $q->orWhere(function ($subQ) use ($tags) {
                    foreach ($tags as $tag) {
                        $subQ->orWhere('tags', 'LIKE', "%{$tag}%");
                    }
                });
            }

            // 2. Category
            if ($product->category_id) {
                $q->orWhere('category_id', $product->category_id);
            }

            // 3. Producer
            $q->orWhere('producer_profile_id', $product->producer_profile_id);
        });

        $products = $query->with('category', 'producer.user', 'reviews.user')
            ->orderBy('download_count', 'desc')
            ->take($limit)
            ->get();

        return ProductResource::collection($products);
    }
}
