<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hangout;
use App\Models\JoinRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JoinRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = JoinRequest::with(['hangout', 'user.profile'])->latest();

        if ($request->filled('host_id')) {
            $query->whereHas('hangout', function ($hangoutQuery) use ($request) {
                $hangoutQuery->where('host_id', $request->integer('host_id'));
            });
        }

        return response()->json(
            $query->get()->map(fn (JoinRequest $joinRequest) => $this->formatJoinRequest($joinRequest))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hangout_id' => ['required', 'exists:hangouts,id'],
            'user_id' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $hangout = Hangout::findOrFail($validated['hangout_id']);

        if ((int) $hangout->host_id === (int) $validated['user_id']) {
            return response()->json(['message' => 'Host is already part of this hangout.'], 422);
        }

        $joinRequest = JoinRequest::updateOrCreate(
            [
                'hangout_id' => $validated['hangout_id'],
                'user_id' => $validated['user_id'],
            ],
            [
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
            ]
        );

        return response()->json(
            $this->formatJoinRequest($joinRequest->load(['hangout', 'user.profile'])),
            $joinRequest->wasRecentlyCreated ? 201 : 200
        );
    }

    public function update(Request $request, JoinRequest $joinRequest): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'declined'])],
        ]);

        DB::transaction(function () use ($joinRequest, $validated) {
            $joinRequest->update(['status' => $validated['status']]);

            if ($validated['status'] === 'approved') {
                $joinRequest->hangout->members()->syncWithoutDetaching([
                    $joinRequest->user_id => ['joined_at' => now()],
                ]);
            }
        });

        return response()->json(
            $this->formatJoinRequest($joinRequest->fresh(['hangout', 'user.profile']))
        );
    }

    private function formatJoinRequest(JoinRequest $joinRequest): array
    {
        $user = $joinRequest->user;
        $profile = $user->profile;

        return [
            'id' => $joinRequest->id,
            'hangout_id' => $joinRequest->hangout_id,
            'hangout_title' => $joinRequest->hangout?->title ?? 'Unknown hangout',
            'user' => [
                'name' => $user->name,
                'age' => $profile?->age ?? 0,
                'city' => $profile?->city ?? '',
                'bio' => $profile?->bio ?? '',
                'avatar_url' => $profile?->avatar_url ?? 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
                'is_verified' => (bool) ($profile?->is_verified ?? false),
                'vibe_tags' => [],
            ],
            'notes' => $joinRequest->notes ?? '',
            'status' => $joinRequest->status,
        ];
    }
}
