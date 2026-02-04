<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProducerProfile;
use App\Http\Resources\PublicProfileResource;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // GET /api/profiles/me
    public function me(Request $request)
    {
        $user = $request->user();
        
        if (!$user->producerProfile) {
             return response()->json(['message' => 'Prodcuer profile not found'], 404);
        }

        $profile = $user->producerProfile()->withCount(['products', 'followers'])->firstOrFail();

        return new PublicProfileResource($profile);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        
        if (!$user->producerProfile) {
            return response()->json(['message' => 'Producer profile not found'], 404);
        }

        $request->validate([
            'display_name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'social_links' => 'nullable|array',
            'avatar' => 'nullable|image|max:2048', // 2MB Max
            'cover_image' => 'nullable|image|max:4096', // 4MB Max
        ]);

        $profile = $user->producerProfile;

        // Handle Avatar Upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($profile->avatar_path) {
                \Illuminate\Support\Facades\Storage::delete($profile->avatar_path);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $profile->avatar_path = $path;
        }

        // Handle Cover Image Upload
        if ($request->hasFile('cover_image')) {
            // Delete old cover if exists
             if ($profile->cover_path) {
                \Illuminate\Support\Facades\Storage::delete($profile->cover_path);
            }
            $path = $request->file('cover_image')->store('covers', 'public');
            $profile->cover_path = $path;
        }

        $profile->update([
            'display_name' => $request->display_name,
            'bio' => $request->bio,
            'location' => $request->location,
            'website' => $request->website,
            'social_links' => $request->social_links,
        ]);

        return new PublicProfileResource($profile);
    }

    // GET /api/profiles/{id}
    public function show($id)
    {
        $profile = ProducerProfile::withCount(['products', 'followers'])
            ->findOrFail($id);

        return new PublicProfileResource($profile);
    }

    // GET /api/profiles/{id}/sounds
    public function sounds($id)
    {
        $profile = ProducerProfile::findOrFail($id);
        
        $products = $profile->products()
            ->latest()
            ->paginate(12);

        return ProductResource::collection($products);
    }

    // GET /api/profiles/{id}/collections
    public function collections($id)
    {
        $profile = ProducerProfile::findOrFail($id);
        
        $query = $profile->user->collections()->with('products');

        // If not the owner, show only public
        if (request()->user('sanctum')?->id !== $profile->user_id) {
            $query->where('is_public', true);
        }

        return $query->latest()->get();
    }

    // POST /api/profiles/{id}/follow
    public function toggleFollow(Request $request, $id)
    {
        $profile = ProducerProfile::findOrFail($id);
        $user = $request->user();

        // Prevent following yourself
        if ($profile->user_id === $user->id) {
            return response()->json(['message' => 'Cannot follow yourself'], 422);
        }

        $isFollowing = $profile->followers()->where('follower_id', $user->id)->exists();

        if ($isFollowing) {
            $profile->followers()->detach($user->id);
            $action = 'unfollowed';
        } else {
            $profile->followers()->attach($user->id);
            $action = 'followed';
        }

        return response()->json([
            'message' => "Successfully $action " . $profile->display_name,
            'is_following' => !$isFollowing,
            'followers_count' => $profile->followers()->count()
        ]);
    }
}