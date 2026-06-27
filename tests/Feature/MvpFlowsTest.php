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

    public function test_members_can_reply_react_edit_and_delete_their_messages(): void
    {
        $member = $this->user();
        $host = $this->user('host');
        $hangout = $this->hangout($host);
        $hangout->members()->attach($member->id, ['role' => 'member', 'status' => 'active', 'joined_at' => now()]);
        Sanctum::actingAs($member);

        $first = $this->postJson("/api/v1/hangouts/{$hangout->id}/messages", ['body' => 'First message'])->assertCreated()->json('data');
        $reply = $this->postJson("/api/v1/hangouts/{$hangout->id}/messages", ['body' => 'Reply', 'reply_to_id' => $first['id']])
            ->assertCreated()->assertJsonPath('data.reply_to.id', $first['id'])->json('data');
        $this->postJson("/api/v1/messages/{$reply['id']}/reactions", ['emoji' => '❤️'])->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/v1/messages/{$reply['id']}", ['body' => 'Edited reply'])->assertOk()->assertJsonPath('data.message_text', 'Edited reply');
        $this->deleteJson("/api/v1/messages/{$reply['id']}")->assertNoContent();
        $this->assertSoftDeleted('group_messages', ['id' => $reply['id']]);
    }

    public function test_user_cannot_edit_another_members_message(): void
    {
        $member = $this->user();
        $other = $this->user();
        $host = $this->user('host');
        $hangout = $this->hangout($host);
        $hangout->members()->attach($member->id, ['role' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $hangout->members()->attach($other->id, ['role' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $message = $hangout->messages()->create(['sender_id' => $other->id, 'message_text' => 'Private ownership', 'type' => 'message']);
        Sanctum::actingAs($member);

        $this->putJson("/api/v1/messages/{$message->id}", ['body' => 'Tampered'])->assertForbidden();
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

    public function test_user_can_favorite_venues_and_hangouts(): void
    {
        $user = $this->user();
        $hangout = $this->hangout($this->user('host'));
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/venues/{$hangout->venue_id}/favorite")->assertCreated();
        $this->postJson("/api/v1/hangouts/{$hangout->id}/favorite")->assertCreated();
        $this->getJson('/api/v1/favorites')->assertOk()->assertJsonCount(2, 'data.data');
        $this->getJson('/api/v1/hangouts')->assertJsonPath('data.data.0.is_favorited', true);
    }

    public function test_full_hangout_waitlists_and_promotes_the_oldest_request(): void
    {
        $host = $this->user('host');
        $member = $this->user();
        $waiting = $this->user();
        $hangout = $this->hangout($host);
        $hangout->update(['group_size_limit' => 2, 'status' => 'full']);
        $hangout->members()->attach($member->id, ['role' => 'member', 'status' => 'active', 'joined_at' => now()]);

        Sanctum::actingAs($waiting);
        $this->postJson("/api/v1/hangouts/{$hangout->id}/join-requests")
            ->assertCreated()->assertJsonPath('data.status', 'waitlisted');

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/hangouts/{$hangout->id}/leave")->assertOk();
        $this->assertDatabaseHas('join_requests', ['hangout_id' => $hangout->id, 'user_id' => $waiting->id, 'status' => 'pending']);
    }

    public function test_invite_code_resolves_to_a_shareable_hangout(): void
    {
        $viewer = $this->user();
        $hangout = $this->hangout($this->user('host'));
        $hangout->update(['invite_code' => 'nightout1234']);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/invites/nightout1234')->assertOk()
            ->assertJsonPath('data.hangout.id', $hangout->id)
            ->assertJsonPath('data.share_url', 'natsvibe://hangouts/nightout1234');
    }

    public function test_completed_hangout_members_can_build_a_reputation_history(): void
    {
        $host = $this->user('host');
        $member = $this->user();
        $hangout = $this->hangout($host, 'completed', now()->subDay());
        $hangout->members()->attach($member->id, ['role' => 'member', 'status' => 'active', 'joined_at' => now()->subDays(2)]);
        Sanctum::actingAs($host);

        $this->postJson("/api/v1/hangouts/{$hangout->id}/peer-reviews", [
            'reviewed_user_id' => $member->id, 'rating' => 2, 'attendance' => 'no_show',
            'safety_concern' => true, 'private_notes' => 'Did not arrive or communicate.',
        ])->assertCreated();

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/users/{$member->id}/reputation")->assertOk()
            ->assertJsonPath('data.no_show_strikes', 1)->assertJsonPath('data.safety_flags', 1)
            ->assertJsonPath('data.average_rating', 2);
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
