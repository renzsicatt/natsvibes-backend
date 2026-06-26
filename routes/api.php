<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\GroupMessageController;
use App\Http\Controllers\Api\HangoutController;
use App\Http\Controllers\Api\JoinRequestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SafetyCheckinController;
use App\Http\Controllers\Api\TrustedContactController;
use App\Http\Controllers\Api\VenueController;
use App\Http\Controllers\Api\VibeTagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,60');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,60');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('me', [ProfileController::class, 'me']);
        Route::put('me/profile', [ProfileController::class, 'updateMe']);
        Route::post('me/profile/photo', [ProfileController::class, 'uploadPhoto']);
        Route::delete('me', [ProfileController::class, 'requestDeletion']);
        Route::post('device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroy']);

        // Pending members may browse while completing verification, but all
        // community mutations remain behind the active-account middleware.
        Route::get('venues', [VenueController::class, 'index']);
        Route::get('venues/{venue}', [VenueController::class, 'show']);
        Route::get('vibe-tags', [VibeTagController::class, 'index']);
        Route::get('hangouts', [HangoutController::class, 'index']);
        Route::get('hangouts/{hangout}', [HangoutController::class, 'show']);
        Route::get('me/join-requests', [JoinRequestController::class, 'mine']);
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('me/hangouts', [HangoutController::class, 'myHangouts']);
        Route::post('me/host-verification', [ProfileController::class, 'requestHostVerification']);
        Route::post('hangouts', [HangoutController::class, 'store'])->middleware('role:host,admin,super_admin');
        Route::put('hangouts/{hangout}', [HangoutController::class, 'update']);
        Route::post('hangouts/{hangout}/cancel', [HangoutController::class, 'cancel']);
        Route::post('hangouts/{hangout}/complete', [HangoutController::class, 'complete']);

        Route::post('hangouts/{hangout}/join-requests', [JoinRequestController::class, 'store'])->middleware('throttle:10,1');
        Route::get('hangouts/{hangout}/join-requests', [JoinRequestController::class, 'index']);
        Route::post('join-requests/{joinRequest}/approve', [JoinRequestController::class, 'approve']);
        Route::post('join-requests/{joinRequest}/decline', [JoinRequestController::class, 'decline']);
        Route::post('join-requests/{joinRequest}/cancel', [JoinRequestController::class, 'cancel']);
        Route::post('hangouts/{hangout}/leave', [JoinRequestController::class, 'leave']);

        Route::get('hangouts/{hangout}/messages', [GroupMessageController::class, 'index']);
        Route::post('hangouts/{hangout}/messages', [GroupMessageController::class, 'store'])->middleware('throttle:20,1');
        Route::post('hangouts/{hangout}/announcements', [GroupMessageController::class, 'announcement']);

        Route::apiResource('blocks', BlockController::class)->only(['index', 'store', 'destroy']);
        Route::apiResource('trusted-contacts', TrustedContactController::class)->except(['show']);
        Route::post('reports', [ReportController::class, 'store']);
        Route::get('me/reports', [ReportController::class, 'mine']);
        Route::post('hangouts/{hangout}/safety-checkins', [SafetyCheckinController::class, 'store']);
        Route::put('safety-checkins/{safetyCheckin}', [SafetyCheckinController::class, 'update']);
        Route::post('safety-checkins/{safetyCheckin}/safe', [SafetyCheckinController::class, 'safe']);
        Route::post('safety-checkins/{safetyCheckin}/help', [SafetyCheckinController::class, 'help']);
        Route::post('hangouts/{hangout}/attendance', [FeedbackController::class, 'attendance']);
        Route::post('hangouts/{hangout}/feedback', [FeedbackController::class, 'feedback']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);

        Route::prefix('admin')->middleware('role:admin,super_admin')->group(function (): void {
            Route::apiResource('venues', VenueController::class)->except(['index', 'show']);
            Route::get('reports', [ReportController::class, 'index']);
            Route::put('reports/{report}', [ReportController::class, 'update']);
            Route::get('verifications', [ProfileController::class, 'verifications']);
            Route::put('verifications/{profile}', [ProfileController::class, 'verify']);
            Route::post('vibe-tags', [VibeTagController::class, 'store']);
        });
    });
});
