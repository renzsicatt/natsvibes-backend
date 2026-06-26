<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hangout;
use App\Models\SafetyCheckin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafetyCheckinController extends Controller
{
    public function store(Request $request, Hangout $hangout): JsonResponse
    {
        abort_unless($hangout->activeMembers()->whereKey($request->user()->id)->exists(), 403);
        $validated = $request->validate(['scheduled_for' => ['required', 'date', 'after:now']]);
        $checkin = SafetyCheckin::updateOrCreate(
            ['user_id' => $request->user()->id, 'hangout_id' => $hangout->id],
            ['scheduled_for' => $validated['scheduled_for'], 'reminder_time' => $validated['scheduled_for'], 'status' => 'scheduled'],
        );

        return response()->json(['data' => $checkin], $checkin->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, SafetyCheckin $safetyCheckin): JsonResponse
    {
        $this->own($request, $safetyCheckin);
        $validated = $request->validate(['scheduled_for' => ['required', 'date', 'after:now']]);
        $safetyCheckin->update(['scheduled_for' => $validated['scheduled_for'], 'reminder_time' => $validated['scheduled_for'], 'status' => 'scheduled']);

        return response()->json(['data' => $safetyCheckin]);
    }

    public function safe(Request $request, SafetyCheckin $safetyCheckin): JsonResponse
    {
        $this->own($request, $safetyCheckin);
        $safetyCheckin->update(['status' => 'safe', 'responded_at' => now(), 'checkin_time' => now()]);

        return response()->json(['data' => $safetyCheckin]);
    }

    public function help(Request $request, SafetyCheckin $safetyCheckin): JsonResponse
    {
        $this->own($request, $safetyCheckin);
        $safetyCheckin->update(['status' => 'help_requested', 'responded_at' => now(), 'escalated_at' => now()]);

        return response()->json(['data' => $safetyCheckin]);
    }

    private function own(Request $request, SafetyCheckin $checkin): void
    {
        abort_unless($checkin->user_id === $request->user()->id, 403);
    }
}
