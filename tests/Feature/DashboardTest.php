<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProducerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_endpoint_returns_producer_scoped_data()
    {
        // 1. Create Producer User
        $user = User::factory()->create();
        $profile = ProducerProfile::factory()->create(['user_id' => $user->id]);

        // 2. Create another producer (noise)
        $otherUser = User::factory()->create();
        $otherProfile = ProducerProfile::factory()->create(['user_id' => $otherUser->id]);

        // 3. Create Products
        $myProduct = Product::factory()->create([
            'producer_profile_id' => $profile->id,
            'price' => 50
        ]);
        $otherProduct = Product::factory()->create([
            'producer_profile_id' => $otherProfile->id,
            'price' => 100
        ]);

        // 4. Create Orders
        // Order 1: Buys my product
        $order1 = Order::factory()->create(['user_id' => $otherUser->id, 'status' => 'completed']);
        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $myProduct->id,
            'price' => 50
        ]);

        // Order 2: Buys other product
        $order2 = Order::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $otherProduct->id,
            'price' => 100
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson('/api/dashboard/stats');

        // Assert
        $response->assertStatus(200)
             ->assertJsonFragment([
                'label' => 'Total Revenue',
                'value' => 50,
             ])
             ->assertJsonFragment([
                'label' => 'Total Sounds',
                'value' => 1,
             ]);
    }

    public function test_recent_sales_endpoint_returns_only_producer_sales()
    {
        // Setup similar to above
        $user = User::factory()->create();
        $profile = ProducerProfile::factory()->create(['user_id' => $user->id]);
        
        $buyer = User::factory()->create(['name' => 'The Buyer']);

        $myProduct = Product::factory()->create([
            'producer_profile_id' => $profile->id,
            'name' => 'My Hit Song'
        ]);

        $order = Order::factory()->create(['user_id' => $buyer->id, 'status' => 'completed']);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $myProduct->id,
            'price' => 25.00
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson('/api/dashboard/recent-sales');

        // Assert
        $response->assertStatus(200)
            ->assertJsonFragment([
                'customer_name' => 'The Buyer',
                'product_name' => 'My Hit Song',
                'amount' => 25.00
            ]);
    }
    
    public function test_non_producers_cannot_access_dashboard()
    {
         $user = User::factory()->create(); // No profile
         
         $this->actingAs($user)
            ->getJson('/api/dashboard/stats')
            ->assertStatus(403);
    }
}
