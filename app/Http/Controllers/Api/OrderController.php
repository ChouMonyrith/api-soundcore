<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Services\BakongKHQRService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sound;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
class OrderController extends Controller
{
    protected BakongKHQRService $bakong;

    public function __construct(BakongKHQRService $bakong)
    {
        $this->bakong = $bakong;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $this->authorize('viewAny', Order::class);

        $orders = $user->orders()
                    ->with(['orderItems.product:id,slug,name,preview_path'])
                    ->orderBy('created_at','desc')
                    ->paginate($request->get('per_page',10));

        return response()->json([
            'orders' => OrderResource::collection($orders),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'payment_method' => 'required|string|in:stripe,paypal,khqr',
        ]);

        try {
            $result = DB::transaction(function () use ($user, $validated) {

                $cartItems = $user->carts()
                    ->with(['product:id,price,name,slug,image_path'])
                    ->lockForUpdate()
                    ->get();

                if ($cartItems->isEmpty()) {
                    throw new \RuntimeException('Cart is empty.');
                }

                $totalPrice = 0;
                $orderItemsData = [];

                foreach ($cartItems as $cartItem) {
                    $product = $cartItem->product;

                    if (!$product) {
                        throw new \RuntimeException('One or more products are no longer available.');
                    }

                    if ($user->hasPurchased($product->id)) {
                        throw new \RuntimeException(
                            "You have already purchased '{$product->name}'."
                        );
                    }

                    $licenseMultiplier = $cartItem->license_type === 'extended' ? 1.5 : 1.0;
                    $unitPrice = $product->price * $licenseMultiplier;

                    $lineTotal = $unitPrice * $cartItem->quantity;
                    $totalPrice += $lineTotal;

                    $orderItemsData[] = [
                        'product_id'  => $product->id,
                        'price'       => $unitPrice,
                        'license_type'=> $cartItem->license_type,
                        'quantity'    => $cartItem->quantity,
                    ];
                }

                // Generate KHQR (external service)
                $khqr = $this->bakong->generateMerchantQR($totalPrice);

                if (
                    empty($khqr['md5']) ||
                    empty($khqr['payload'])
                ) {
                    throw new \RuntimeException('Failed to generate Bakong QR.');
                }

                $order = Order::create([
                    'transaction_id'   => uniqid('trx_'),
                    'user_id'          => $user->id,
                    'total'            => $totalPrice,
                    'subtotal'         => $totalPrice,
                    'tax'              => 0,
                    'payment_method'   => $validated['payment_method'],
                    'status'           => 'pending',
                    'billing_email'    => $user->email,
                    'md5'              => $khqr['md5'],
                    'payment_metadata' => [
                        'order_items_data' => $orderItemsData,
                    ],
                ]);

                return [
                    'order'       => $order,
                    'qr_payload'  => $khqr['payload'],
                    'md5'         => $khqr['md5'],
                    'total_price' => $totalPrice,
                ];
            });

            return response()->json([
                'message'      => 'Bakong payment initiated successfully',
                'qr_payload'   => $result['qr_payload'],
                'md5'          => $result['md5'],
                'order_id'     => $result['order']->id,
                'total_price'  => $result['total_price'],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Bakong payment initiation failed', [
                'user_id' => $user->id,
                'payment_method' => $request->payment_method,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while initiating payment.',
            ], 500);
        }
    }

    public function show(Request $request,string $id)
    {
        $user = $request->user();
        $order = Order::with(['orderItems.product:id,name'])
                    ->findOrFail($id);

        $this->authorize('view',$order);

        return response()->json([
            'order' => new OrderResource($order)
        ]);
    }

    public function checkStatus(Request $request) : JsonResponse 
    {
        
        $request->validate([
            'md5' => 'required|string'
        ]);
        
        $md5 = $request->input('md5');

        $order = Order::where('md5', $md5)->first();

        if (!$order) {
            return response()->json([
                'message'=> 'Payment not found.',
                'payment_status' => 'not_found'
            ], 404);
        }
        
        $user = $order->user;
        if (!$user) {
            Log::error('User not found for order ID.', ['order_id' => $order->id]);
            return response()->json(['status' => 'error', 'message' => 'Associated user not found.'], 500);
        }

        try{
            Log::info('checking payment for md5: ' .$md5);
            
            $result = $this->bakong->checkPaymentStatus($md5);

            if (isset($result['responseCode']) && $result['responseCode'] === 0 &&
                isset($result['responseMessage']) && strtolower($result['responseMessage']) === 'success') 
            {
                if ($order->status !== 'paid') { // Prevent duplicate processing
                    DB::transaction(function () use ($order, $user) {
                        
                        // Decode the stored order items data
                        $orderItemsData = $order->payment_metadata['order_items_data'] ?? [];

                        // Create OrderItem records 
                        foreach ($orderItemsData as $itemData) {
                            OrderItem::create([
                                'order_id' => $order->id,
                                'product_id' => $itemData['product_id'],
                                'price' => $itemData['price'],
                                'license_type' => $itemData['license_type'],
                                'quantity' => $itemData['quantity'],
                            ]);
                        }

                        // Update Sound download counts and Creator balances
                        foreach ($orderItemsData as $itemData) {
                            $product = Product::find($itemData['product_id']);
                            if ($product) {
                                $product->increment('download_count', $itemData['quantity']);
                                $earnings = $itemData['price'] * 0.70; // Example: 70% to creator
                                if ($product->user) {
                                    $product->user->increment('balance', $earnings * $itemData['quantity']);
                                }
                            }
                        }

                        // Clear the user's cart
                        $user->carts()->delete();

                        // Create Download records for each purchased item
                        foreach ($orderItemsData as $itemData) {
                            \App\Models\Download::create([
                                'user_id' => $user->id,
                                'product_id' => $itemData['product_id'],
                                'order_id' => $order->id,
                                'ip_address' => request()->ip(),
                                'downloaded_at' => now(),
                            ]);
                        }

                        // Update the Order record status and paid_at timestamp
                        $order->update([
                            'status' => 'paid',
                            'paid_at' => now(), // Set the paid timestamp
                        ]);

                        $user->producerProfile()->update([
                            'sales_count' => DB::raw('sales_count + 1'),
                        ]);
                    });

                    

                    Log::info('Order finalized successfully from confirmed Bakong payment.', [
                        'order_id' => $order->id,
                        'md5' => $md5
                    ]);

                    // Send notification
                    $user->notify(new \App\Notifications\OrderPaid($order));

                    return response()->json([
                        'payment_status' => 'paid',
                        'order_id' => $order->id,
                        'bakong_response' => $result
                    ]);
                } else {
                    return response()->json([
                        'status' => 'paid',
                        'order_id' => $order->id,
                        'bakong_response' => $result
                    ]);
                }

            } else {        
                return response()->json([
                    'payment_status' => strtolower($order->status ?? 'pending'),
                    'result' => $result
                ]);
            }


        } catch (\Exception $e) {
            Log::error('Bakong API error: '.$e->getMessage());
            return response()->json(['payment_status' => 'bakong_error', 'message' => $e->getMessage()], 500);
        }
    }

    public function download(Request $request, Product $product)
    {
        $user = $request->user();
        
        // $product is already filtered/resolved by route binding withTrashed()


        // 1. Verify User has purchased this product and order is PAID
        $hasPurchased = $user->orders()
            ->where('status', 'paid')
            ->whereHas('orderItems', function($q) use ($product) {
                $q->where('product_id', $product->id);
            })
            ->exists();

        if (!$hasPurchased) {
            abort(403, 'You have not purchased this product.');
        }

        if (!Storage::disk('private')->exists($product->file_path)) {
            abort(404, 'File not found on server.');
        }

        // 3. Serve the file
        return response()->download(
            Storage::disk('private')->path($product->file_path), 
            $product->name . '.' . pathinfo($product->file_path, PATHINFO_EXTENSION)
        );
    }
}
