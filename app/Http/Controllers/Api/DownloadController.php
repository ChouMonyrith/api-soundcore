<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Download;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class DownloadController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Fetch downloads for the user, including product details and producer
        $downloads = Download::with(['product' => function ($query) {
            $query->withTrashed();
        }, 'product.producer'])
            ->where('user_id', $user->id)
            ->orderBy('downloaded_at', 'desc')
            ->get();

        
        $data = $downloads->map(function ($download) {
            return [
                'id' => $download->id,
                'created_at' => $download->downloaded_at, // Use the timestamp from connection
                'license_type' => 'Standard', // Default for now, or fetch from OrderItem if we link it deeper
                'download_url' => URL::to('/api/orders/download/' . $download->product->id), // Using ID for robust lookup in OrderController
                'product' => [
                    'id' => $download->product->id,
                    'name' => $download->product->name,
                    'artist' => $download->product->producer->display_name ?? 'SoundCore Artist', // Assuming relationship exists or fallback
                    'image_path' => $download->product->image_path ? URL::to('/api/storage/'.$download->product->image_path) : null,
                    'file_path' => $download->product->file_path ? URL::to('/api/storage/'.$download->product->file_path) : null,
                ]
            ];
        });

        return response()->json(['data' => $data]);
    }
}
