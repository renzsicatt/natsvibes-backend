<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->load('profile.vibeTags')]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'city' => ['sometimes', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'going_out_style' => ['nullable', 'string', 'max:100'],
            'availability' => ['nullable', 'string', 'max:255'],
            'safety_preference' => ['nullable', 'string', 'max:255'],
            'vibe_tag_ids' => ['sometimes', 'array', 'max:10'],
            'vibe_tag_ids.*' => ['integer', 'exists:vibe_tags,id'],
        ]);

        $profile = $request->user()->profile;
        $profile->update(collect($validated)->except('vibe_tag_ids')->all());
        if (array_key_exists('vibe_tag_ids', $validated)) {
            $profile->vibeTags()->sync($validated['vibe_tag_ids']);
        }

        $complete = filled($profile->display_name) && filled($profile->city) && filled($profile->bio);
        $profile->update(['completion_status' => $complete ? 'completed' : 'incomplete']);

        return response()->json(['data' => $profile->fresh('vibeTags')]);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:8192']]);
        $profile = $request->user()->profile;
        if ($profile->avatar_url && ! str_starts_with($profile->avatar_url, 'http')) {
            Storage::disk('public')->delete($profile->avatar_url);
        }
        $path = $request->file('photo')->store('profile-photos', 'public');
        $profile->update(['avatar_url' => $path, 'photo_review_status' => 'pending', 'verification_status' => 'pending']);

        return response()->json(['data' => ['path' => $path, 'review_status' => 'pending']], 201);
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $request->user()->update(['status' => 'deletion_pending', 'deletion_requested_at' => now()]);
        $request->user()->tokens()->delete();

        return response()->json(['data' => ['message' => 'Account deletion was scheduled.']]);
    }

    public function verifications(): JsonResponse
    {
        return response()->json(['data' => Profile::with('user')->where('verification_status', 'pending')->latest()->paginate(25)]);
    }

    public function verify(Request $request, Profile $profile): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'declined'])],
            'host_status' => ['nullable', Rule::in(['not_requested', 'pending', 'approved', 'declined'])],
        ]);

        DB::transaction(function () use ($request, $profile, $validated): void {
            $profile->update([
                'verification_status' => $validated['status'],
                'photo_review_status' => $validated['status'],
                'host_verification_status' => $validated['host_status'] ?? $profile->host_verification_status,
            ]);

            $userChanges = ['status' => $validated['status'] === 'approved' ? 'active' : 'pending_verification'];
            if (($validated['host_status'] ?? null) === 'approved') {
                $userChanges['role'] = 'host';
            }
            $profile->user->update($userChanges);

            DB::table('admin_actions')->insert([
                'admin_id' => $request->user()->id,
                'action_type' => 'profile_verification_updated',
                'details' => json_encode($validated),
                'target_type' => Profile::class,
                'target_id' => $profile->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return response()->json(['data' => $profile->fresh('user')]);
    }
}
