<?php

namespace Tests\Feature;

use App\Models\Hangout;
use App\Models\Profile;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MvpSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_never_auto_approves_a_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User', 'email' => 'new@example.com', 'phone' => '+639171234567',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => 'strongpass123', 'password_confirmation' => 'strongpass123',
        ]);

        $response->assertCreated()->assertJsonPath('data.user.status', 'pending_verification');
        $this->assertDatabaseHas('profiles', ['verification_status' => 'pending', 'completion_status' => 'incomplete']);
    }

    public function test_underage_registration_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Minor', 'email' => 'minor@example.com', 'phone' => '+639171234568',
            'date_of_birth' => now()->subYears(17)->toDateString(),
            'password' => 'strongpass123', 'password_confirmation' => 'strongpass123',
        ])->assertUnprocessable();
    }

    public function test_regular_user_cannot_create_a_hangout_or_admin_venue(): void
    {
        $user = $this->eligibleUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/hangouts', [])->assertForbidden();
        $this->postJson('/api/v1/admin/venues', [])->assertForbidden();
    }

    public function test_join_request_identity_comes_from_authentication(): void
    {
        $host = $this->eligibleUser('host');
        $guest = $this->eligibleUser();
        $other = $this->eligibleUser();
        $hangout = $this->hangout($host);
        Sanctum::actingAs($guest);

        $this->postJson("/api/v1/hangouts/{$hangout->id}/join-requests", [
            'user_id' => $other->id, 'message' => 'Hello',
        ])->assertCreated();

        $this->assertDatabaseHas('join_requests', ['hangout_id' => $hangout->id, 'user_id' => $guest->id]);
        $this->assertDatabaseMissing('join_requests', ['hangout_id' => $hangout->id, 'user_id' => $other->id]);
    }

    public function test_only_host_can_approve_and_capacity_is_enforced(): void
    {
        $host = $this->eligibleUser('host');
        $guest = $this->eligibleUser();
        $attacker = $this->eligibleUser();
        $hangout = $this->hangout($host, 3);
        $request = $hangout->joinRequests()->create(['user_id' => $guest->id, 'status' => 'pending']);

        Sanctum::actingAs($attacker);
        $this->postJson("/api/v1/join-requests/{$request->id}/approve")->assertForbidden();

        Sanctum::actingAs($host);
        $this->postJson("/api/v1/join-requests/{$request->id}/approve")->assertOk();
        $this->assertDatabaseHas('hangout_members', ['hangout_id' => $hangout->id, 'user_id' => $guest->id, 'status' => 'active']);
    }

    private function eligibleUser(string $role = 'user'): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        Profile::create([
            'user_id' => $user->id, 'name' => $user->name, 'display_name' => $user->name,
            'city' => 'Makati', 'bio' => 'Ready to join.', 'completion_status' => 'completed',
            'verification_status' => 'approved', 'photo_review_status' => 'approved',
            'host_verification_status' => $role === 'host' ? 'approved' : 'not_requested',
        ]);

        return $user->fresh('profile');
    }

    private function hangout(User $host, int $capacity = 6): Hangout
    {
        $venue = Venue::create([
            'name' => 'Test Venue', 'slug' => 'test-venue-'.uniqid(), 'area' => 'Poblacion',
            'city' => 'Makati', 'address' => 'Public address', 'venue_type' => 'Bar',
            'price_range' => '$$', 'status' => 'listed',
        ]);
        $hangout = Hangout::create([
            'host_id' => $host->id, 'venue_id' => $venue->id, 'title' => 'Test Night',
            'date_time' => now()->addDay(), 'request_cutoff_at' => now()->addHours(20),
            'area' => 'Poblacion', 'group_size_limit' => $capacity, 'budget_range' => '$$', 'status' => 'open',
        ]);
        $hangout->members()->attach($host->id, ['role' => 'host', 'status' => 'active', 'joined_at' => now()]);

        return $hangout;
    }
}
