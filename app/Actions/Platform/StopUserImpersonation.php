<?php

namespace App\Actions\Platform;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class StopUserImpersonation
{
    public function __construct(private Impersonation $impersonation) {}

    public function handle(Request $request): User
    {
        $impersonator = $this->impersonation->impersonator($request);

        if ($impersonator === null || ! $impersonator->isPlatformAdmin()) {
            throw new InvalidArgumentException('No active platform impersonation session.');
        }

        $target = $request->user();

        Auth::login($impersonator);
        $request->session()->forget(Impersonation::SESSION_KEY);
        $request->session()->regenerate();

        Log::info('Platform admin stopped user impersonation.', [
            'impersonator_id' => $impersonator->id,
            'impersonator_email' => $impersonator->email,
            'target_id' => $target?->id,
            'target_email' => $target?->email,
        ]);

        return $impersonator->fresh(['currentTeam']);
    }
}
