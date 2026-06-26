<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);
        abort_unless(str_starts_with($validated['token'], 'ExponentPushToken[') || str_starts_with($validated['token'], 'ExpoPushToken['), 422, 'Invalid Expo push token.');

        $token = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [...$validated, 'user_id' => $request->user()->id, 'last_seen_at' => now()],
        );

        return response()->json(['data' => $token], $token->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, DeviceToken $deviceToken): JsonResponse
    {
        abort_unless($deviceToken->user_id === $request->user()->id, 403);
        $deviceToken->delete();

        return response()->json(null, 204);
    }
}
