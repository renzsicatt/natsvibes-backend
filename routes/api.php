<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VenueController;
use App\Http\Controllers\Api\HangoutController;
use App\Http\Controllers\Api\JoinRequestController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\VibeTagController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GroupMessageController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/me', function (Request $request) {
    $user = $request->user()->load('profile.vibeTags');
    $profile = $user->profile;
    return response()->json([
        'user' => $user,
        'profile' => [
            'name' => $user->name,
            'age' => $profile?->age ?? 24,
            'city' => $profile?->city ?? '',
            'bio' => $profile?->bio ?? '',
            'avatar_url' => $profile?->avatar_url ?? 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
            'is_verified' => $profile ? ($profile->verification_status === 'approved') : false,
            'vibe_tags' => $profile ? $profile->vibeTags->pluck('name')->toArray() : [],
        ]
    ]);
})->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/my-hangouts', [HangoutController::class, 'myHangouts'])->middleware('auth:sanctum');
Route::get('/hangouts/{hangout}/messages', [GroupMessageController::class, 'index'])->middleware('auth:sanctum');
Route::post('/hangouts/{hangout}/messages', [GroupMessageController::class, 'store'])->middleware('auth:sanctum');

Route::apiResource('venues', VenueController::class);
Route::apiResource('hangouts', HangoutController::class)->only(['index', 'show', 'store']);
Route::apiResource('join-requests', JoinRequestController::class)
    ->only(['index', 'store', 'update'])
    ->parameters(['join-requests' => 'joinRequest']);
Route::get('/join-request', [JoinRequestController::class, 'index']);
Route::post('/join-request', [JoinRequestController::class, 'store']);
Route::patch('/join-request/{joinRequest}', [JoinRequestController::class, 'update']);

Route::get('/profiles/verifications', [ProfileController::class, 'verifications']);
Route::put('/profiles/{id}/verify', [ProfileController::class, 'verify']);
Route::apiResource('reports', ReportController::class)->only(['index', 'update']);
Route::apiResource('vibe-tags', VibeTagController::class)->only(['index', 'store']);
