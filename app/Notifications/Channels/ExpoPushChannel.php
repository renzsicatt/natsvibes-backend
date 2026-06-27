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
        $request = Http::timeout(8);
        if ($token = config('services.expo.access_token')) {
            $request = $request->withToken($token);
        }
        $request->post(config('services.expo.endpoint'), $payloads)->throw();
    }
}
