<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModerationAppeal;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AppealController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'statement' => ['required', 'string', 'min:20', 'max:5000']]);
        $user = User::where('email', mb_strtolower($validated['email']))->first();
        abort_unless($user && Hash::check($validated['password'], $user->password), 422, 'Credentials are invalid.');
        abort_unless(in_array($user->status, ['suspended', 'banned'], true), 409, 'This account is not eligible for an appeal.');
        abort_if(ModerationAppeal::where('user_id', $user->id)->where('status', 'pending')->exists(), 409, 'A pending appeal already exists.');
        $appeal = ModerationAppeal::create(['user_id' => $user->id, 'account_status' => $user->status, 'statement' => $validated['statement'], 'status' => 'pending']);

        return response()->json(['data' => ['id' => $appeal->id, 'status' => $appeal->status]], 201);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => ModerationAppeal::with('user.profile')->latest()->cursorPaginate(25)]);
    }

    public function decide(Request $request, ModerationAppeal $appeal): JsonResponse
    {
        abort_unless($appeal->status === 'pending', 409);
        $validated = $request->validate(['decision' => ['required', Rule::in(['approved', 'declined'])], 'notes' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $appeal, $validated): void {
            $appeal->update(['status' => $validated['decision'], 'decision_notes' => $validated['notes'], 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            if ($validated['decision'] === 'approved') {
                $appeal->user->update(['status' => 'active', 'suspended_until' => null, 'banned_at' => null]);
            }
            DB::table('admin_actions')->insert(['admin_id' => $request->user()->id, 'action_type' => 'appeal_'.$validated['decision'], 'details' => json_encode($validated), 'target_type' => ModerationAppeal::class, 'target_id' => $appeal->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::afterCommit(fn () => $appeal->user->notify(new ActivityNotification('appeal_'.$validated['decision'], ['appeal_id' => $appeal->id])));
        });

        return response()->json(['data' => $appeal->fresh(['user', 'admin'])]);
    }
}
