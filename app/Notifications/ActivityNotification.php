<?php

namespace App\Notifications;

use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $event, private readonly array $payload = []) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->notificationPreference?->push_enabled ?? true) {
            $channels[] = ExpoPushChannel::class;
        }
        if (config('natsvibe.transactional_email_enabled') && ($notifiable->notificationPreference?->email_enabled ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->toExpoPush($notifiable);

        return (new MailMessage)->subject($message['title'].' update')->line($message['body'])->line('Open NatsVibe for details.');
    }

    public function toArray(object $notifiable): array
    {
        return ['event' => $this->event, ...$this->payload];
    }

    public function toExpoPush(object $notifiable): array
    {
        $messages = [
            'join_request_received' => 'A member requested to join your hangout.',
            'join_request_approved' => 'Your join request was approved.',
            'join_request_declined' => 'Your join request was declined.',
            'hangout_cancelled' => 'A hangout you joined was cancelled.',
            'safety_checkin_reminder' => 'Time for your safety check-in.',
            'profile_verification_updated' => 'Your profile verification was updated.',
            'host_verification_updated' => 'Your host verification was updated.',
        ];

        return [
            'title' => 'NatsVibe',
            'body' => $messages[$this->event] ?? 'You have a new NatsVibe update.',
            'data' => ['event' => $this->event, ...$this->payload],
            'sound' => 'default',
            'channelId' => 'default',
        ];
    }
}
