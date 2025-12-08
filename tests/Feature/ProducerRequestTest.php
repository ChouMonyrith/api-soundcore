<?php

namespace Tests\Feature;

use App\Models\ProducerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProducerRequestTest extends TestCase
{
    use RefreshDatabase; // Use with caution if using shared DB, assuming testing DB or transaction

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure roles exist
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'producer']);
    }

    public function test_user_can_submit_producer_request()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/producer/request', [
            'display_name' => 'Test Producer',
            'bio' => 'I make beats.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('producer_profiles', [
            'user_id' => $user->id,
            'status' => 'pending',
            'display_name' => 'Test Producer'
        ]);
    }

    public function test_admin_can_approve_producer_request()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $profile = ProducerProfile::create([
            'user_id' => $user->id,
            'display_name' => 'Aspiring Producer',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($admin)->postJson("/api/producer/request/{$profile->id}/approve");

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('producer_profiles', [
            'id' => $profile->id,
            'status' => 'approved'
        ]);

        $this->assertTrue($user->refresh()->hasRole('producer'));
    }

    public function test_admin_can_reject_producer_request()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $profile = ProducerProfile::create([
            'user_id' => $user->id,
            'display_name' => 'Bad Producer',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($admin)->postJson("/api/producer/request/{$profile->id}/reject");

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('producer_profiles', [
            'id' => $profile->id,
            'status' => 'rejected'
        ]);

        $this->assertFalse($user->refresh()->hasRole('producer'));
    }

    public function test_non_admin_cannot_approve_request()
    {
        $user = User::factory()->create(); // Regular user
        $targetUser = User::factory()->create();
        $profile = ProducerProfile::create([
            'user_id' => $targetUser->id,
            'display_name' => 'Target',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($user)->postJson("/api/producer/request/{$profile->id}/approve");

        $response->assertStatus(403); // Forbidden
    }
}
