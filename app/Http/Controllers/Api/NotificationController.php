<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notifications();
        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        return response()->json(['data' => $query->cursorPaginate(25)]);
    }

    public function read(Request $request, DatabaseNotification $notification): JsonResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id && $notification->notifiable_type === $request->user()::class, 403);
        $notification->markAsRead();

        return response()->json(['data' => $notification]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['data' => ['message' => 'Notifications marked as read.']]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->notificationPreference()->firstOrCreate()->refresh()]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $fields = ['push_enabled', 'email_enabled', 'join_updates', 'hangout_updates', 'safety_updates'];
        $validated = $request->validate(collect($fields)->mapWithKeys(
            fn (string $field) => [$field => ['sometimes', Rule::in([true, false, 0, 1, '0', '1'])]],
        )->all());
        $preference = $request->user()->notificationPreference()->firstOrCreate();
        $preference->update($validated);

        return response()->json(['data' => $preference->fresh()]);
    }
}
