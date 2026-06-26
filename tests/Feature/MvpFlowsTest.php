<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Hangout;
use App\Models\Profile;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MvpFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_only_returns_future_public_hangouts(): void
    {
        $user = $this->user();
        $host = $this->user('host');
        $visible = $this->hangout($host, 'open', now()->addDay());
        $this->hangout($host, 'draft', now()->addDays(2));
        $this->hangout($host, 'open', now()->subDay());
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/hangouts')->assertOk()
            ->assertJsonFragment(['id' => $visible->id])
            ->assertJsonCount(1, 'data.data');
    }

    public function test_private_host_notes_are_hidden_until_membership(): void
    {
        $viewer = $this->user();
        $host = $this->user('host');
        $hangout = $this->hangout($host);
        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/hangouts/{$hangout->id}")->assertOk()->assertJsonMissing(['host_notes' => 'Meet by the private code NV1']);

        $hangout->members()->attach($viewer->id, ['role' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $this->getJson("/api/v1/hangouts/{$hangout->id}")->assertJsonPath('data.host_notes', 'Meet by the private code NV1');
    }

    public function test_only_active_members_can_read_or_send_chat(): void
    {
        $outsider = $this->user();
        $member = $this->user();
        $host = $this->user('host');
        $hangout = $this->hangout($host);
        $hangout->members()->attach($member->id, ['role' => 'member', 'status' => 'active', 'joined_at' => now()]);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/hangouts/{$hangout->id}/messages")->assertForbidden();
        Sanctum::actingAs($member);
        $this->postJson("/api/v1/hangouts/{$hangout->id}/messages", ['body' => 'See you there'])->assertCreated();
    }

    public function test_blocked_user_cannot_request_host_hangout(): void
    {
        $guest = $this->user();
        $host = $this->user('host');
        $hangout = $this->hangout($host);
        Block::create(['blocker_id' => $host->id, 'blocked_id' => $guest->id]);
        Sanctum::actingAs($guest);
        $this->postJson("/api/v1/hangouts/{$hangout->id}/join-requests", [])->assertForbidden();
    }

    public function test_user_can_report_and_only_admin_can_view_moderation_queue(): void
    {
        $user = $this->user();
        $target = $this->user();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/reports', ['reported_user_id' => $target->id, 'reason' => 'harassment', 'details' => 'Repeated unwanted contact.'])->assertCreated();
        $this->getJson('/api/v1/admin/reports')->assertForbidden();

        $admin = $this->user('admin');
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/reports')->assertOk()->assertJsonCount(1, 'data.data');
    }

    private function user(string $role = 'user'): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        Profile::create(['user_id' => $user->id, 'name' => $user->name, 'display_name' => $user->name, 'city' => 'Makati', 'bio' => 'Complete profile', 'completion_status' => 'completed', 'verification_status' => 'approved', 'photo_review_status' => 'approved', 'host_verification_status' => $role === 'host' ? 'approved' : 'not_requested']);

        return $user->fresh('profile');
    }

    private function hangout(User $host, string $status = 'open', $date = null): Hangout
    {
        $venue = Venue::create(['name' => 'Venue '.uniqid(), 'slug' => 'venue-'.uniqid(), 'area' => 'Poblacion', 'city' => 'Makati', 'address' => 'Public address', 'venue_type' => 'Bar', 'price_range' => '$$', 'status' => 'listed']);
        $date ??= now()->addDay();
        $hangout = Hangout::create(['host_id' => $host->id, 'venue_id' => $venue->id, 'title' => 'Night '.uniqid(), 'description' => 'Public description', 'host_notes' => 'Meet by the private code NV1', 'date_time' => $date, 'request_cutoff_at' => $date->copy()->subHours(2), 'area' => 'Poblacion', 'group_size_limit' => 6, 'budget_range' => '$$', 'status' => $status]);
        $hangout->members()->attach($host->id, ['role' => 'host', 'status' => 'active', 'joined_at' => now()]);

        return $hangout;
    }
}
