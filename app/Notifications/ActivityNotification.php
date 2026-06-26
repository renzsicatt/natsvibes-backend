<?php

namespace App\Notifications;

use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ActivityNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $event, private readonly array $payload = []) {}

    public function via(object $notifiable): array
    {
        return ['database', ExpoPushChannel::class];
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
