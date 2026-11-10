<?php

namespace App\Actions\Platform;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class StartUserImpersonation
{
    public function __construct(private Impersonation $impersonation) {}

    public function handle(Request $request, User $actor, User $target): User
    {
        if (! $actor->isPlatformAdmin()) {
            throw new InvalidArgumentException('Only platform administrators can impersonate users.');
        }

        if ($this->impersonation->active($request)) {
            throw new InvalidArgumentException('Leave the current impersonation before starting another.');
        }

        if ($actor->is($target)) {
            throw new InvalidArgumentException('You cannot impersonate yourself.');
        }

        if ($target->isPlatformAdmin()) {
            throw new InvalidArgumentException('Platform administrators cannot be impersonated.');
        }

        Auth::login($target);
        $request->session()->put(Impersonation::SESSION_KEY, $actor->id);
        $request->session()->regenerate();

        if ($target->currentTeam === null) {
            $firstTeam = $target->teams()->first();
            if ($firstTeam !== null) {
                $target->switchTeam($firstTeam);
            }
        }

        Log::info('Platform admin started user impersonation.', [
            'impersonator_id' => $actor->id,
            'impersonator_email' => $actor->email,
            'target_id' => $target->id,
            'target_email' => $target->email,
        ]);

        return $target->fresh(['currentTeam']);
    }
}
