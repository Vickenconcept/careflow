<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\StartUserImpersonation;
use App\Actions\Platform\StopUserImpersonation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;

class ImpersonationController extends Controller
{
    public function store(
        Request $request,
        User $user,
        StartUserImpersonation $startUserImpersonation,
    ): RedirectResponse {
        try {
            $target = $startUserImpersonation->handle($request, $request->user(), $user);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['impersonation' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Now viewing as :name.', ['name' => $target->name]),
        ]);

        if ($target->currentTeam !== null) {
            return to_route('dashboard', [
                'current_team' => $target->currentTeam->slug,
            ]);
        }

        return redirect('/');
    }

    public function destroy(
        Request $request,
        StopUserImpersonation $stopUserImpersonation,
    ): RedirectResponse {
        try {
            $stopUserImpersonation->handle($request);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['impersonation' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Impersonation ended.'),
        ]);

        return to_route('platform.dashboard');
    }
}
