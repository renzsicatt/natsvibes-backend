<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_preferences_can_be_read_and_updated(): void
    {
        $user = $this->activeUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notification-preferences')->assertOk()->assertJsonPath('data.push_enabled', true);
        $this->putJson('/api/v1/notification-preferences', ['push_enabled' => false])
            ->assertOk()->assertJsonPath('data.push_enabled', false);
    }

    public function test_report_accepts_private_evidence(): void
    {
        Storage::fake('local');
        config(['filesystems.evidence_disk' => 'local']);
        $reporter = $this->activeUser();
        $target = $this->activeUser();
        Sanctum::actingAs($reporter);

        $this->postJson('/api/v1/reports', [
            'reported_user_id' => $target->id,
            'reason' => 'harassment',
            'details' => 'Repeated unwanted contact.',
            'evidence' => [UploadedFile::fake()->image('evidence.jpg')],
        ])->assertCreated()->assertJsonCount(1, 'data.evidence');

        $evidence = Report::firstOrFail()->evidence()->firstOrFail();
        Storage::disk('local')->assertExists($evidence->path);
    }

    public function test_admin_routes_can_require_mfa_enrollment(): void
    {
        config(['natsvibe.admin_mfa_required' => true]);
        $admin = $this->activeUser('admin');
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/reports')->assertForbidden();
        $this->postJson('/api/v1/admin/mfa/setup')->assertOk()->assertJsonStructure(['data' => ['secret', 'otpauth_uri']]);
    }

    public function test_expired_deletion_request_is_anonymized(): void
    {
        $user = $this->activeUser();
        $user->update(['status' => 'deletion_pending', 'deletion_requested_at' => now()->subDays(31)]);

        $this->artisan('accounts:anonymize-deleted')->assertSuccessful();

        $deleted = User::withTrashed()->findOrFail($user->id);
        $this->assertSame('deleted', $deleted->status);
        $this->assertStringStartsWith('deleted+', $deleted->email);
        $this->assertNotNull($deleted->deleted_at);
    }

    public function test_health_endpoint_and_request_id_are_available(): void
    {
        $this->getJson('/api/v1/health', ['X-Request-ID' => 'test-request-id'])
            ->assertOk()->assertHeader('X-Request-ID', 'test-request-id')
            ->assertJsonPath('data.status', 'ok');
    }

    private function activeUser(string $role = 'user'): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        Profile::create([
            'user_id' => $user->id, 'name' => $user->name, 'display_name' => $user->name,
            'city' => 'Makati', 'bio' => 'Complete profile', 'completion_status' => 'completed',
            'verification_status' => 'approved', 'photo_review_status' => 'approved',
            'host_verification_status' => 'not_requested',
        ]);

        return $user->fresh('profile');
    }
}
