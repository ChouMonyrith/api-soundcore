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

        // Validate the request data
        $request->validate([
            'payment_method' => 'required|string|in:stripe,paypal,khqr', 
        ]);

        try{

            $cartItems = $user->carts()->with(['product:id,price,name,slug,image_path'])->get(); 

            if ($cartItems->isEmpty()) {
                throw new \Exception('Cart is empty.');
            }

            $totalPrice = 0;
            $orderItemsData = [];

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->product;
                // Check if sound exists AND has the required status
                if (!$product) {
                    $productName = $product ? ($product->name ?? 'Unknown (ID: '.$product->id.')') : 'Unknown';
                    throw new \Exception("Product '{$productName}' is no longer available.");
                }

                // Determine final price based on license type
                $licenseMultiplier = $cartItem->license_type === 'extended' ? 1.5 : 1.0;
                $itemPrice = $cartItem->product->price * $licenseMultiplier;

                $totalPrice += $itemPrice * $cartItem->quantity; 

                $orderItemsData[] = [
                    'product_id' => $cartItem->product->id,
                    'price' => $itemPrice, 
                    'license_type' => $cartItem->license_type,
                    'quantity' => $cartItem->quantity,
                ];
            }
       
            $khqr = $this->bakong->generateMerchantQR($totalPrice);

            if(!isset($khqr['md5']) || !isset($khqr['payload'])) {
                 Log::error('Bakong service failed to generate QR.', [
                    'user_id' => $user->id,
                    'total_price' => $totalPrice,
                    'qr_result' => $khqr,
                ]);
                return response()->json(['message' => 'Failed to generate payment QR code.'], 500);
            }

            // Create the main Order record
            $order = Order::create([
                'transaction_id' => uniqid('trx_'),
                'user_id' => $user->id,
                'total' => $totalPrice,
                'subtotal' => $totalPrice, // Assuming no tax for now or inclusive
                'tax' => 0,
                'payment_method' => $request->payment_method,
                'status' => 'pending', 
                'billing_email' => $user->email,
                'md5' => $khqr['md5'],
                'payment_metadata' => [
                    'order_items_data' => $orderItemsData
                ]
            ]);

            

            return response()->json([
                'message'=> 'Bakong payment initiated successfully',
                'qr_payload' => $khqr['payload'],
                'md5' => $khqr['md5'],
                'order_id' => $order->id,
                'total_price' => $totalPrice,
            ], 200);

        }catch (\Exception $e) {
            Log::error('Bakong payment initiation failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'An error occurred while initiating payment: ' . $e->getMessage()], 500);
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

        // 2. Check if file exists
        if (!Storage::disk('private')->exists($product->file_path)) {
            abort(404, 'File not found on server.');
        }

        // 3. Serve the file
        return Storage::disk('private')->download($product->file_path, $product->name . '.' . pathinfo($product->file_path, PATHINFO_EXTENSION));
    }
}
