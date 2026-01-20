<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProducerProfile;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendingTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_trending_tags_excludes_deleted_products()
    {
        // 1. Setup Data
        $user = User::factory()->create();
        $producer = ProducerProfile::factory()->create(['user_id' => $user->id]);
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'producer_profile_id' => $producer->id,
            'category_id' => $category->id,
            'tags' => ['lo-fi', 'chill'], // Make sure tags are set
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        // Manually create OrderItem as factory might not exist
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
        ]);

        // 2. Soft Delete (or Hard Delete) the product
        // ProductController uses "whereHas('product')", which respects soft deletes if SoftDeletes trait is used.
        // If Product model doesn't use SoftDeletes, then delete() removes the row.
        // Let's check if Product uses SoftDeletes. 
        // Based on previous file read, Product model did NOT show SoftDeletes trait.
        // So a standard delete() will remove the row from `products` table.
        // But OrderItem still points to that product_id.
        // This is exactly the scenario causing the crash (OrderItem exists, Product doesn't).
        
        $product->delete();

        // 3. Call the endpoint
        $response = $this->getJson('/api/tags/trending');

        // 4. Assertions
        $response->assertStatus(200);
        // Ensure "lo-fi" is NOT in the list (since product is deleted)
        $response->assertJsonMissing(['lo-fi']);
    }
}
