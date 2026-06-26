<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hangout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function attendance(Request $request, Hangout $hangout): JsonResponse
    {
        abort_unless($hangout->activeMembers()->whereKey($request->user()->id)->exists(), 403);
        $validated = $request->validate(['response' => ['required', Rule::in(['attended', 'did_not_attend', 'cancelled'])]]);
        DB::table('attendance_responses')->updateOrInsert(
            ['hangout_id' => $hangout->id, 'user_id' => $request->user()->id],
            ['response' => $validated['response'], 'responded_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        );

        return response()->json(['data' => $validated]);
    }

    public function feedback(Request $request, Hangout $hangout): JsonResponse
    {
        abort_unless($hangout->status === 'completed' && $hangout->activeMembers()->whereKey($request->user()->id)->exists(), 403);
        $validated = $request->validate([
            'host_rating' => ['nullable', 'integer', 'between:1,5'],
            'group_vibe_rating' => ['nullable', 'integer', 'between:1,5'],
            'venue_rating' => ['nullable', 'integer', 'between:1,5'],
            'safety_concern' => ['sometimes', 'boolean'],
            'private_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::table('hangout_feedback')->updateOrInsert(
            ['hangout_id' => $hangout->id, 'reviewer_id' => $request->user()->id],
            [...$validated, 'created_at' => now(), 'updated_at' => now()],
        );

        return response()->json(['data' => $validated]);
    }
}
