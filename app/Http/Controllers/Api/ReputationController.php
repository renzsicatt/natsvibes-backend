<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hangout;
use App\Models\PeerReview;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReputationController extends Controller
{
    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->is($user) || $request->user()->isAdmin(), 403);
        $reviews = PeerReview::where('reviewed_user_id', $user->id);

        return response()->json(['data' => [
            'user_id' => $user->id,
            'average_rating' => round((float) ($reviews->clone()->avg('rating') ?? 0), 2),
            'review_count' => $reviews->clone()->count(),
            'attended_count' => $reviews->clone()->where('attendance', 'attended')->count(),
            'no_show_strikes' => $reviews->clone()->where('attendance', 'no_show')->count(),
            'safety_flags' => $reviews->clone()->where('safety_concern', true)->count(),
        ]]);
    }

    public function store(Request $request, Hangout $hangout): JsonResponse
    {
        abort_unless($hangout->status === 'completed', 409, 'Reviews open after the hangout is completed.');
        abort_unless($hangout->activeMembers()->whereKey($request->user()->id)->exists(), 403);
        $validated = $request->validate([
            'reviewed_user_id' => ['required', 'integer', 'different:reviewer_id', 'exists:users,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'attendance' => ['required', Rule::in(['attended', 'no_show', 'cancelled'])],
            'safety_concern' => ['sometimes', 'boolean'],
            'private_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_if((int) $validated['reviewed_user_id'] === $request->user()->id, 422, 'You cannot review yourself.');
        abort_unless($hangout->activeMembers()->whereKey($validated['reviewed_user_id'])->exists(), 422, 'You can only review a hangout member.');
        $review = PeerReview::updateOrCreate(
            ['hangout_id' => $hangout->id, 'reviewer_id' => $request->user()->id, 'reviewed_user_id' => $validated['reviewed_user_id']],
            $validated,
        );

        return response()->json(['data' => $review], 201);
    }
}
