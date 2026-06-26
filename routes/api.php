<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VenueController;
use App\Http\Controllers\Api\HangoutController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\VibeTagController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('venues', VenueController::class);
Route::apiResource('hangouts', HangoutController::class)->only(['index', 'show', 'store']);

Route::get('/profiles/verifications', [ProfileController::class, 'verifications']);
Route::put('/profiles/{id}/verify', [ProfileController::class, 'verify']);
Route::apiResource('reports', ReportController::class)->only(['index', 'update']);
Route::apiResource('vibe-tags', VibeTagController::class)->only(['index', 'store']);

