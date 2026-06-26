<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        [$user, $token] = DB::transaction(function () use ($validated): array {
            $user = User::create([
                'name' => $validated['name'],
                'email' => mb_strtolower($validated['email']),
                'phone' => $validated['phone'],
                'date_of_birth' => $validated['date_of_birth'],
                'role' => 'user',
                'status' => 'pending_verification',
                'password' => Hash::make($validated['password']),
            ]);

            Profile::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'display_name' => $user->name,
                'completion_status' => 'incomplete',
                'verification_status' => 'pending',
                'photo_review_status' => 'pending',
                'host_verification_status' => 'not_requested',
            ]);

            return [$user, $user->createToken($validated['device_name'] ?? 'mobile')->plainTextToken];
        });

        return response()->json(['data' => ['token' => $token, 'user' => $this->userData($user)]], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', mb_strtolower($validated['email']))->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['error' => ['code' => 'INVALID_CREDENTIALS', 'message' => 'The provided credentials are invalid.']], 422);
        }

        if (! in_array($user->status, ['active', 'pending_verification'], true)) {
            return response()->json(['error' => ['code' => 'ACCOUNT_UNAVAILABLE', 'message' => 'This account cannot sign in.']], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken($validated['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json(['data' => ['token' => $token, 'user' => $this->userData($user)]]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        PasswordBroker::sendResetLink(['email' => mb_strtolower($validated['email'])]);

        return response()->json(['data' => ['message' => 'If that account exists, a reset link has been sent.']]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'], 'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $status = PasswordBroker::reset($validated, function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
        });
        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return response()->json(['error' => ['code' => 'RESET_FAILED', 'message' => __($status)]], 422);
        }

        return response()->json(['data' => ['message' => __($status)]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['data' => ['message' => 'Logged out.']]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['data' => ['message' => 'All sessions were logged out.']]);
    }

    private function userData(User $user): array
    {
        $user->loadMissing('profile.vibeTags');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'profile' => $user->profile,
        ];
    }
}
