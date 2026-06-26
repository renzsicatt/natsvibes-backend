<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle Email & Password Login.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials do not match our records.'
            ], 422);
        }

        // Generate Sanctum token
        $token = $user->createToken('mobile-token')->plainTextToken;

        // Load profile relationship
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

    /**
     * Handle Sign Up / Registration.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|unique:users,phone',
            'password' => 'required|string|min:6',
        ]);

        // Create the user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        // Create corresponding profile
        Profile::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'age' => 24,
            'city' => 'Makati',
            'bio' => 'Vibing, chill drinks & friendly talks.',
            'avatar_url' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
            'completion_status' => 'completed',
            'verification_status' => 'approved', // Auto-approved for instant testing
        ]);

        // Generate Sanctum token
        $token = $user->createToken('mobile-token')->plainTextToken;

        // Load profile with vibeTags
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
        ], 201);
    }
}
