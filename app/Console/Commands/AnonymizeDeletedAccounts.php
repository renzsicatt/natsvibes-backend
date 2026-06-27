<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnonymizeDeletedAccounts extends Command
{
    protected $signature = 'accounts:anonymize-deleted {--dry-run}';

    protected $description = 'Anonymize accounts whose deletion grace period has elapsed';

    public function handle(): int
    {
        $cutoff = now()->subDays(config('natsvibe.account_deletion_grace_days'));
        $users = User::with('profile')->where('status', 'deletion_pending')->where('deletion_requested_at', '<=', $cutoff)->get();
        if ($this->option('dry-run')) {
            $this->info($users->count().' account(s) eligible.');

            return self::SUCCESS;
        }
        $users->each(function (User $user): void {
            DB::transaction(function () use ($user): void {
                $suffix = Str::uuid()->toString();
                $avatar = $user->profile?->avatar_url;
                if ($avatar && ! str_starts_with($avatar, 'http')) {
                    Storage::disk(config('filesystems.profile_photos_disk'))->delete($avatar);
                }
                $user->tokens()->delete();
                $user->deviceTokens()->delete();
                $user->trustedContacts()->delete();
                $user->notificationPreference()->delete();
                $user->favorites()->delete();
                $user->profile?->update([
                    'name' => 'Deleted user', 'display_name' => 'Deleted user', 'city' => null,
                    'bio' => null, 'avatar_url' => null, 'going_out_style' => null,
                    'availability' => null, 'safety_preference' => null,
                ]);
                $user->forceFill([
                    'name' => 'Deleted user', 'email' => "deleted+{$suffix}@invalid.local", 'phone' => null,
                    'date_of_birth' => null, 'password' => Str::random(64), 'status' => 'deleted',
                    'admin_mfa_secret' => null, 'admin_mfa_confirmed_at' => null,
                ])->save();
                $user->delete();
            });
        });
        $this->info($users->count().' account(s) anonymized.');

        return self::SUCCESS;
    }
}
