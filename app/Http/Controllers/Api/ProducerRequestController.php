<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProducerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProducerRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Admin only: list all requests with status
        $requests = ProducerProfile::with('user')
            ->where('status', 'pending') // default filter, or use request param
            ->latest()
            ->paginate(10);
            
        return response()->json($requests);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if ($user->producerProfile) {
            return response()->json(['message' => 'You already have a producer profile request.'], 400);
        }

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            // add avatar validation if needed
        ]);

        $profile = ProducerProfile::create([
            'user_id' => $user->id,
            'display_name' => $validated['display_name'],
            'bio' => $validated['bio'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json($profile, 201);
    }

    /**
     * Approve the specialized producer request
     */
    public function approve($id)
    {
        $profile = ProducerProfile::findOrFail($id);
        
        $profile->update(['status' => 'approved']);
        
        $user = $profile->user;
        $user->assignRole('producer'); // Ensure 'producer' role exists in seeder

        return response()->json(['message' => 'Producer request approved', 'profile' => $profile]);
    }

    /**
     * Reject the specialized producer request
     */
    public function reject($id)
    {
        $profile = ProducerProfile::findOrFail($id);
        
        $profile->update(['status' => 'rejected']);
        
        return response()->json(['message' => 'Producer request rejected', 'profile' => $profile]);
    }
}
