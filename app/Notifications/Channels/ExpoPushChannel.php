<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class ExpoPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $tokens = $notifiable->deviceTokens()->pluck('token');
        if ($tokens->isEmpty() || ! method_exists($notification, 'toExpoPush')) {
            return;
        }

        $message = $notification->toExpoPush($notifiable);
        $payloads = $tokens->map(fn (string $token): array => ['to' => $token, ...$message])->values()->all();
        Http::timeout(8)->post('https://exp.host/--/api/v2/push/send', $payloads)->throw();
    }
}
