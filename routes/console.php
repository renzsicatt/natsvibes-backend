<?php

use App\Models\Hangout;
use App\Models\SafetyCheckin;
use App\Notifications\ActivityNotification;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    Hangout::whereIn('status', ['open', 'full'])->where('date_time', '<=', now())
        ->update(['status' => 'ongoing']);

    Hangout::where('status', 'ongoing')->where('date_time', '<=', now()->subHours(6))
        ->update(['status' => 'completed']);

    SafetyCheckin::with('user')->where('status', 'scheduled')->where('scheduled_for', '<=', now())
        ->each(function (SafetyCheckin $checkin): void {
            $checkin->update(['status' => 'reminder_sent', 'reminded_at' => now()]);
            $checkin->user->notify(new ActivityNotification('safety_checkin_reminder', ['checkin_id' => $checkin->id, 'hangout_id' => $checkin->hangout_id]));
        });

    User::where('status', 'suspended')->where('suspended_until', '<=', now())
        ->each(function (User $user): void {
            $user->update(['status' => 'active', 'suspended_until' => null]);
            $user->notify(new ActivityNotification('account_restore', ['reason' => 'Suspension period completed.']));
        });
})->everyMinute()->name('natsvibe-lifecycle')->withoutOverlapping();

Schedule::command('accounts:anonymize-deleted')->dailyAt('02:30')->name('account-anonymization')->withoutOverlapping();

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
