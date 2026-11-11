<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    /**
     * When the deployment requires two-factor authentication, send users who have
     * not set it up to the security page before they can reach patient data.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            config('careflow.require_two_factor')
            && $user !== null
            && ! $user->hasEnabledTwoFactorAuthentication()
        ) {
            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
