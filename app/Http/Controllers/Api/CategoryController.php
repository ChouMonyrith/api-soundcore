<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     * Public route (usually).
     */
    public function index()
    {
        // Optimization: 'oldest' or 'name' sort is usually better for UI than default ID sort
        $categories = Category::with('products')->orderBy('name')->get();
        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Security Check
        if (!auth()->user()->can('manage categories')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name', // Added unique check
        ]);

        // 2. SEO Friendly Slug (Remove uniqid unless strictly necessary)
        // If unique validation passes above, Str::slug is usually safe enough.
        $validated['slug'] = Str::slug($validated['name']); 

        // 3. Fix Race Condition
        $category = Category::create($validated); 

        return response()->json([
            'message' => 'Category created successfully',
            'category' => new CategoryResource($category), // Use the instance directly
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = Category::with('products')->findOrFail($id);
        return new CategoryResource($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if (!auth()->user()->can('manage categories')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
        ]);

        // 4. SEO Fix: Don't change the slug automatically on update
        // We only update the name. If you MUST update slug, check if name changed.
        if ($category->name !== $validated['name']) {
             $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => new CategoryResource($category),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // 5. Added Missing Security Check
        if (!auth()->user()->can('manage categories')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $category = Category::findOrFail($id);

        // 6. Prevent SQL Error (Constraint Violation)
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category. It contains products.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}