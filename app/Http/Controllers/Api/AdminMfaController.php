<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMfaController extends Controller
{
    public function setup(Request $request, Totp $totp): JsonResponse
    {
        $secret = $totp->secret();
        $request->user()->forceFill(['admin_mfa_secret' => $secret, 'admin_mfa_confirmed_at' => null])->save();
        $issuer = rawurlencode(config('app.name'));
        $label = rawurlencode(config('app.name').':'.$request->user()->email);

        return response()->json(['data' => ['secret' => $secret, 'otpauth_uri' => "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&digits=6&period=30"]]);
    }

    public function confirm(Request $request, Totp $totp): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        abort_unless($request->user()->admin_mfa_secret && $totp->verify($request->user()->admin_mfa_secret, $validated['code']), 422, 'Invalid authenticator code.');
        $request->user()->forceFill(['admin_mfa_confirmed_at' => now()])->save();
        $request->user()->currentAccessToken()?->delete();
        $token = $request->user()->createToken('admin-mfa', ['mfa:verified'])->plainTextToken;

        return response()->json(['data' => ['enabled' => true, 'token' => $token]]);
    }
}
