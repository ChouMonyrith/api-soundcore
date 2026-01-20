<?php

namespace Tests\Feature;

use App\Models\Download;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeletedProductDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_download_deleted_product()
    {
        Storage::fake('private');

        // 1. Setup Data
        $user = User::factory()->create();
        $product = Product::factory()->create();
        
        // Create a dummy file
        $file = UploadedFile::fake()->create('song.mp3', 100);
        $path = $file->store('products/files', 'private');
        $product->update(['file_path' => $path]);

        // Create Order and OrderItem (Paid)
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'paid']);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
        ]);
        
        // Create Download record
        Download::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'downloaded_at' => now(),
        ]);

        // 2. Soft Delete the product
        $product->delete();

        // 3. Check "My Downloads" list
        $responseList = $this->actingAs($user)->getJson('/api/my-downloads');
        $responseList->assertStatus(200);
        
        // Verify product is in the list
        $responseList->assertJsonFragment(['id' => $product->id]);
        $responseList->assertJsonFragment(['name' => $product->name]);

        // 4. Attempt Download
        $responseDownload = $this->actingAs($user)->get("/api/orders/download/{$product->id}");
        
        // 5. Assertions
        if ($responseDownload->status() !== 200) {
            dump($responseDownload->json());
        }
        
        $responseDownload->assertStatus(200);
        $responseDownload->assertHeader('content-disposition');
    }
}
