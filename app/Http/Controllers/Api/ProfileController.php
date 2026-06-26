<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Display a listing of profiles pending verification.
     */
    public function verifications(): JsonResponse
    {
        $profiles = Profile::where('verification_status', 'pending')->get();
        
        $formatted = $profiles->map(function ($profile) {
            return [
                'id' => $profile->id,
                'name' => $profile->name,
                'age' => $profile->age ?? 25,
                'city' => $profile->city ?? 'Makati',
                'photo_url' => $profile->avatar_url ?? 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=300&q=80',
                'status' => $profile->verification_status,
                'requested_at' => $profile->created_at->diffForHumans()
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Approve or decline a profile's verification request.
     */
    public function verify(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:approved,declined'
        ]);

        $profile = Profile::findOrFail($id);
        $profile->update([
            'verification_status' => $validated['status']
        ]);

        return response()->json([
            'id' => $profile->id,
            'status' => $profile->verification_status,
            'message' => 'Profile verification status updated successfully.'
        ]);
    }
}
