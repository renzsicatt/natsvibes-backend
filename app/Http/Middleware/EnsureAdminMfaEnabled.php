<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminMfaEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('natsvibe.admin_mfa_required')) {
            abort_unless($request->user()?->admin_mfa_confirmed_at, 403, 'Admin MFA enrollment is required.');
            abort_unless($request->user()->tokenCan('mfa:verified'), 403, 'Complete the admin MFA challenge.');
        }

        return $next($request);
    }
}
