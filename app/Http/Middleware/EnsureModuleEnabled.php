<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks routes that belong to an optional module the organization switched off.
 * Runs after team membership is verified.
 */
class EnsureModuleEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $team = $request->route('current_team');

        if (is_string($team)) {
            $team = Team::query()->where('slug', $team)->first();
        }

        abort_if(! $team instanceof Team || ! $team->moduleEnabled($module), 403, 'This module is switched off for your organization.');

        return $next($request);
    }
}
