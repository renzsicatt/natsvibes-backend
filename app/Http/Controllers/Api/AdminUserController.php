<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending_verification', 'active', 'suspended', 'banned', 'deletion_pending'])],
            'role' => ['nullable', Rule::in(['user', 'host', 'admin', 'super_admin'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $query = User::with('profile')
            ->withAvg('peerReviewsReceived as reputation_rating', 'rating')
            ->withCount([
                'peerReviewsReceived as reputation_review_count',
                'peerReviewsReceived as no_show_strikes' => fn ($q) => $q->where('attendance', 'no_show'),
                'peerReviewsReceived as safety_flags' => fn ($q) => $q->where('safety_concern', true),
            ])->latest();
        $query->when($validated['status'] ?? null, fn ($q, string $status) => $q->where('status', $status));
        $query->when($validated['role'] ?? null, fn ($q, string $role) => $q->where('role', $role));
        $query->when($validated['search'] ?? null, fn ($q, string $search) => $q->where(fn ($nested) => $nested
            ->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));

        return response()->json(['data' => $query->cursorPaginate(25)]);
    }

    public function moderate(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot moderate your own account.');
        abort_if($user->role === 'super_admin', 403, 'Super-admin accounts cannot be moderated here.');
        abort_if($user->isAdmin() && $request->user()->role !== 'super_admin', 403, 'Only a super admin can moderate another admin.');
        $validated = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'ban', 'restore'])],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'suspended_until' => ['required_if:action,suspend', 'nullable', 'date', 'after:now'],
        ]);

        DB::transaction(function () use ($request, $user, $validated): void {
            $before = $user->only(['status', 'suspended_until', 'banned_at']);
            $changes = match ($validated['action']) {
                'suspend' => ['status' => 'suspended', 'suspended_until' => $validated['suspended_until'], 'banned_at' => null],
                'ban' => ['status' => 'banned', 'suspended_until' => null, 'banned_at' => now()],
                'restore' => ['status' => 'active', 'suspended_until' => null, 'banned_at' => null],
            };
            $user->update($changes);
            if ($validated['action'] !== 'restore') {
                $user->tokens()->delete();
            }
            DB::table('admin_actions')->insert([
                'admin_id' => $request->user()->id,
                'action_type' => 'user_'.$validated['action'],
                'details' => json_encode(['reason' => $validated['reason'], 'before' => $before, 'after' => $changes]),
                'target_type' => User::class, 'target_id' => $user->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::afterCommit(fn () => $user->notify(new ActivityNotification('account_'.$validated['action'], ['reason' => $validated['reason'], 'suspended_until' => $changes['suspended_until'] ?? null])));
        });

        return response()->json(['data' => $user->fresh('profile')]);
    }
}
