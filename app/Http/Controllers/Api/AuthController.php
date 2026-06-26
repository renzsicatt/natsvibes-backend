<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Handle passwordless login or registration.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'phone' => 'nullable|string',
        ]);

        $email = $validated['email'];
        $phone = $validated['phone'] ?? null;

        // Find or create user
        $user = User::where('email', $email)->first();
        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;
            // Extract a readable name from email, e.g. "renz@example.com" -> "Renz"
            $username = explode('@', $email)[0];
            $name = ucfirst(preg_replace('/[^a-zA-Z0-9]/', ' ', $username));

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make(Str::random(16)),
            ]);

            // Create profile
            Profile::create([
                'user_id' => $user->id,
                'name' => $name,
                'age' => 24,
                'city' => 'Makati',
                'bio' => 'Vibing, chill drinks & friendly talks.',
                'avatar_url' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
                'completion_status' => 'completed',
                'verification_status' => 'approved', // Auto-approved for testing/demo convenience
            ]);
        } else {
            if ($phone && $user->phone !== $phone) {
                $user->update(['phone' => $phone]);
            }
        }

        // Generate Sanctum token
        $token = $user->createToken('mobile-token')->plainTextToken;

        // Load profile
        $user->load('profile.vibeTags');
        $profile = $user->profile;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'profile' => [
                'name' => $profile->name,
                'age' => $profile->age ?? 24,
                'city' => $profile->city ?? '',
                'bio' => $profile->bio ?? '',
                'avatar_url' => $profile->avatar_url ?? 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
                'is_verified' => $profile->verification_status === 'approved',
                'vibe_tags' => $profile->vibeTags ? $profile->vibeTags->pluck('name')->toArray() : [],
            ]
        ]);
    }
}
