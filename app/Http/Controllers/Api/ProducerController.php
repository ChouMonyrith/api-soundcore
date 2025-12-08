<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProducerProfileResource;
use App\Http\Resources\ProductResource;
use App\Models\ProducerProfile;
use App\Models\Product;
use Illuminate\Http\Request;

class ProducerController extends Controller
{
    public function show($id)
    {
        $producer = ProducerProfile::with('user')->findOrFail($id);
        return new ProducerProfileResource($producer);
    }

    public function sounds($id)
    {
        $products = Product::where('producer_profile_id', $id)
            ->with(['category', 'producer'])
            ->paginate(12);
            
        return ProductResource::collection($products);
    }
}
