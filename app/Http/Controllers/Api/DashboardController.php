<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();
        
        // Ensure user has a producer profile
        if (!$user->producerProfile) {
            return response()->json(['message' => 'User is not a producer'], 403);
        }

        $producerId = $user->producerProfile->id;

        // Base query for relevant order items (completed orders only)
        $soldItemsQuery = OrderItem::whereHas('order', function ($query) {
            $query->where('status', 'paid');
        })->whereHas('product', function ($query) use ($producerId) {
            $query->where('producer_profile_id', $producerId);
        });

        // Total Revenue (sum of price of sold items belonging to this producer)
        $totalRevenue = $soldItemsQuery->sum('price');

        // Total Sounds (active products owned by producer)
        $totalSounds = Product::where('producer_profile_id', $producerId)->count();

        // Total Downloads (count of sold items)
        $totalDownloads = $soldItemsQuery->count();

        $totalUsers = $soldItemsQuery->with('order')->get()->pluck('order.user_id')->unique()->count();


        return response()->json([
            [
                'label' => 'Total Revenue',
                'value' => $totalRevenue,
                'formatted' => '$' . number_format($totalRevenue, 2),
            ],
            [
                'label' => 'Total Sounds',
                'value' => $totalSounds,
                'formatted' => number_format($totalSounds),
            ],
            [
                'label' => 'Total Downloads',
                'value' => $totalDownloads,
                'formatted' => number_format($totalDownloads),
            ],
            [
                'label' => 'Active Customers',
                'value' => $totalUsers,
                'formatted' => number_format($totalUsers),
            ],
        ]);
    }

    public function recentSales(Request $request)
    {
        $user = $request->user();

        if (!$user->producerProfile) {
            return response()->json(['message' => 'User is not a producer'], 403);
        }

        $producerId = $user->producerProfile->id;
   
        $recentSales = OrderItem::whereHas('order', function ($query) {
                $query->where('status', 'paid');
            })
            ->whereHas('product', function ($query) use ($producerId) {
                $query->where('producer_profile_id', $producerId);
            })
            ->with(['order.user', 'product'])
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(function ($item) {
                $order = $item->order;
                $user = $order->user;
                
                return [
                    'id' => $item->id, // Use Item ID as unique key
                    'order_id' => $order->id,
                    'customer_name' => $user ? $user->name : 'Guest',
                    'customer_avatar' => $user ? substr($user->name, 0, 2) : 'GU',
                    'product_name' => $item->product->name,
                    'amount' => $item->price,
                    'formatted_amount' => '$' . number_format($item->price, 2),
                    'date' => $item->created_at->diffForHumans(),
                ];
            });

        return response()->json($recentSales);
    }
}
