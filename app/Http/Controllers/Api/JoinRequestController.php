<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Hangout;
use App\Models\JoinRequest;
use App\Notifications\ActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JoinRequestController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $items = JoinRequest::query()
            ->with(['hangout.venue', 'hangout.host.profile'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->cursorPaginate(25);

        return response()->json(['data' => $items]);
    }

    public function index(Request $request, Hangout $hangout): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $hangout->host_id === $request->user()->id, 403);

        return response()->json(['data' => $hangout->joinRequests()->with('user.profile.vibeTags')->latest()->cursorPaginate(25)]);
    }

    public function store(Request $request, Hangout $hangout): JsonResponse
    {
        $validated = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);
        $user = $request->user();

        abort_if($hangout->host_id === $user->id, 422, 'Host is already a member.');
        abort_unless($user->profile?->completion_status === 'completed', 403, 'Complete your profile first.');
        abort_unless(in_array($hangout->status, ['open', 'full'], true), 409, 'Hangout is not accepting requests.');
        abort_if($hangout->request_cutoff_at?->isPast() || $hangout->date_time->isPast(), 409, 'The request cutoff has passed.');
        abort_if($this->blockedPairExists($user->id, $hangout->host_id), 403, 'This interaction is unavailable.');

        $joinRequest = JoinRequest::firstOrCreate(
            ['hangout_id' => $hangout->id, 'user_id' => $user->id],
            ['status' => $hangout->status === 'full' ? 'waitlisted' : 'pending', 'notes' => $validated['message'] ?? null],
        );
        abort_unless($joinRequest->wasRecentlyCreated, 409, 'A request already exists for this hangout.');
        if ($joinRequest->status === 'pending') {
            $hangout->host->notify(new ActivityNotification('join_request_received', ['hangout_id' => $hangout->id, 'join_request_id' => $joinRequest->id]));
        }

        return response()->json(['data' => $joinRequest], 201);
    }

    public function approve(Request $request, JoinRequest $joinRequest): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $joinRequest): JoinRequest {
            $locked = JoinRequest::whereKey($joinRequest->id)->lockForUpdate()->firstOrFail();
            $hangout = Hangout::whereKey($locked->hangout_id)->lockForUpdate()->firstOrFail();
            abort_unless($request->user()->isAdmin() || $hangout->host_id === $request->user()->id, 403);
            abort_unless($locked->status === 'pending', 409, 'Request is no longer pending.');
            abort_unless($hangout->status === 'open', 409, 'Hangout is not open.');
            abort_if($hangout->request_cutoff_at?->isPast(), 409, 'The request cutoff has passed.');
            abort_if($this->blockedPairExists($locked->user_id, $hangout->host_id), 409, 'Blocked users cannot be approved.');

            $count = DB::table('hangout_members')->where('hangout_id', $hangout->id)->where('status', 'active')->count();
            abort_if($count >= $hangout->group_size_limit, 409, 'Hangout is full.');

            $locked->update(['status' => 'approved', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            DB::table('hangout_members')->updateOrInsert(
                ['hangout_id' => $hangout->id, 'user_id' => $locked->user_id],
                ['role' => 'member', 'status' => 'active', 'joined_at' => now(), 'left_at' => null, 'created_at' => now(), 'updated_at' => now()],
            );
            if ($count + 1 >= $hangout->group_size_limit) {
                $hangout->update(['status' => 'full']);
            }
            DB::afterCommit(fn () => $locked->user->notify(new ActivityNotification('join_request_approved', ['hangout_id' => $hangout->id])));

            return $locked;
        });

        return response()->json(['data' => $result->fresh(['hangout', 'user.profile'])]);
    }

    public function decline(Request $request, JoinRequest $joinRequest): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $joinRequest->hangout->host_id === $request->user()->id, 403);
        abort_unless($joinRequest->status === 'pending', 409);
        $joinRequest->update(['status' => 'declined', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
        $joinRequest->user->notify(new ActivityNotification('join_request_declined', ['hangout_id' => $joinRequest->hangout_id]));

        return response()->json(['data' => $joinRequest]);
    }

    public function cancel(Request $request, JoinRequest $joinRequest): JsonResponse
    {
        abort_unless($joinRequest->user_id === $request->user()->id && in_array($joinRequest->status, ['pending', 'waitlisted'], true), 403);
        $joinRequest->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return response()->json(['data' => $joinRequest]);
    }

    public function leave(Request $request, Hangout $hangout): JsonResponse
    {
        abort_if($hangout->host_id === $request->user()->id, 422, 'Host must cancel the hangout.');
        $member = DB::table('hangout_members')->where(['hangout_id' => $hangout->id, 'user_id' => $request->user()->id, 'status' => 'active']);
        abort_unless($member->exists(), 404);
        $member->update(['status' => 'withdrawn', 'left_at' => now(), 'updated_at' => now()]);
        JoinRequest::where(['hangout_id' => $hangout->id, 'user_id' => $request->user()->id, 'status' => 'approved'])->update(['status' => 'withdrawn']);
        if ($hangout->status === 'full' && (! $hangout->request_cutoff_at || $hangout->request_cutoff_at->isFuture())) {
            $hangout->update(['status' => 'open']);
            $this->promoteWaitlist($hangout);
        }

        return response()->json(['data' => ['message' => 'You left the hangout.']]);
    }

    private function promoteWaitlist(Hangout $hangout): void
    {
        $next = $hangout->joinRequests()->where('status', 'waitlisted')->oldest()->lockForUpdate()->first();
        if (! $next) {
            return;
        }
        $next->update(['status' => 'pending']);
        DB::afterCommit(function () use ($hangout, $next): void {
            $next->user->notify(new ActivityNotification('waitlist_promoted', ['hangout_id' => $hangout->id]));
            $hangout->host->notify(new ActivityNotification('join_request_received', ['hangout_id' => $hangout->id, 'join_request_id' => $next->id]));
        });
    }

    private function blockedPairExists(int $a, int $b): bool
    {
        return Block::where(fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))
            ->orWhere(fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))->exists();
    }
}
