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

    public function test_pending_user_can_browse_venues_while_verification_is_in_progress(): void
    {
        $user = User::factory()->create(['status' => 'pending_verification']);
        Profile::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'display_name' => $user->name,
            'completion_status' => 'incomplete',
            'verification_status' => 'pending',
        ]);
        Venue::create([
            'name' => 'Browseable Venue',
            'slug' => 'browseable-venue',
            'area' => 'Poblacion',
            'city' => 'Makati',
            'address' => 'Public address',
            'venue_type' => 'Bar',
            'price_range' => '$$',
            'status' => 'listed',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/venues')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Browseable Venue']);
    }

    public function test_pending_user_can_sign_in_while_verification_is_in_progress(): void
    {
        $user = User::factory()->create([
            'email' => 'pending@example.com',
            'password' => 'strongpass123',
            'status' => 'pending_verification',
        ]);
        Profile::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'display_name' => $user->name,
            'completion_status' => 'incomplete',
            'verification_status' => 'pending',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'pending@example.com',
            'password' => 'strongpass123',
            'device_name' => 'test',
        ])->assertOk()
            ->assertJsonPath('data.user.status', 'pending_verification')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_user_can_only_list_their_own_join_requests(): void
    {
        $host = $this->eligibleUser('host');
        $user = $this->eligibleUser();
        $other = $this->eligibleUser();
        $hangout = $this->hangout($host);
        $mine = $hangout->joinRequests()->create(['user_id' => $user->id, 'status' => 'pending']);
        $hangout->joinRequests()->create(['user_id' => $other->id, 'status' => 'pending']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/join-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $mine->id);
    }

    public function test_verified_member_can_request_host_verification(): void
    {
        $user = $this->eligibleUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/host-verification')
            ->assertOk()
            ->assertJsonPath('data.host_verification_status', 'pending');

        $this->postJson('/api/v1/me/host-verification')->assertConflict();
    }

    public function test_profile_approval_creates_a_user_notification(): void
    {
        $admin = $this->eligibleUser('admin');
        $user = User::factory()->create(['status' => 'pending_verification']);
        $profile = Profile::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'display_name' => $user->name,
            'city' => 'Makati',
            'bio' => 'Ready for review.',
            'completion_status' => 'completed',
            'verification_status' => 'pending',
            'photo_review_status' => 'pending',
            'host_verification_status' => 'not_requested',
        ]);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/admin/verifications/{$profile->id}", ['status' => 'approved'])->assertOk();

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
        $this->assertSame('profile_verification_updated', $user->fresh()->notifications()->first()?->data['event']);
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

    public function test_admin_can_suspend_and_restore_a_user_with_an_audit_trail(): void
    {
        $admin = $this->eligibleUser('admin');
        $user = $this->eligibleUser();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/users/{$user->id}/moderate", [
            'action' => 'suspend', 'reason' => 'Repeated safety policy violations.',
            'suspended_until' => now()->addDay()->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertDatabaseHas('admin_actions', ['admin_id' => $admin->id, 'action_type' => 'user_suspend', 'target_id' => $user->id]);

        $this->postJson("/api/v1/admin/users/{$user->id}/moderate", [
            'action' => 'restore', 'reason' => 'Manual review completed successfully.',
        ])->assertOk()->assertJsonPath('data.status', 'active');
    }

    public function test_admin_cannot_moderate_their_own_account(): void
    {
        $admin = $this->eligibleUser('admin');
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/users/{$admin->id}/moderate", ['action' => 'ban', 'reason' => 'Invalid self action.'])
            ->assertUnprocessable();
    }

    public function test_banned_user_can_appeal_and_admin_can_restore_access(): void
    {
        $admin = $this->eligibleUser('admin');
        $user = $this->eligibleUser();
        $user->update(['status' => 'banned', 'banned_at' => now()]);

        $appeal = $this->postJson('/api/v1/auth/appeals', [
            'email' => $user->email, 'password' => 'password',
            'statement' => 'I believe the restriction should be reviewed because I can provide additional context.',
        ])->assertCreated()->json('data');

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/appeals/{$appeal['id']}/decide", [
            'decision' => 'approved', 'notes' => 'Evidence reviewed; access may be restored.',
        ])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame('active', $user->fresh()->status);
        $this->assertDatabaseHas('admin_actions', ['action_type' => 'appeal_approved', 'target_id' => $appeal['id']]);
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
