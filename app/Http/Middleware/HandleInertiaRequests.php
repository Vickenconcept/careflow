<?php

namespace App\Http\Middleware;

use App\Enums\CaseTaskStatus;
use App\Models\CaseTask;
use App\Support\Impersonation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $impersonation = app(Impersonation::class)->sharedState($request);

        $authUser = null;
        if ($user !== null) {
            $authUser = $user->toArray();
            $authUser['is_platform_admin'] = $user->isPlatformAdmin();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $authUser,
            ],
            'impersonation' => $impersonation,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            'assistant' => function () use ($user) {
                $settings = $user?->currentTeam?->organizationSetting;

                return [
                    'enabled' => (bool) ($settings?->assistant_enabled ?? false),
                    'name' => $settings?->assistant_name ?: 'Nora',
                ];
            },
            'pendingWorkCount' => fn () => $user?->currentTeam
                ? CaseTask::query()
                    ->where('team_id', $user->current_team_id)
                    ->where('assigned_user_id', $user->id)
                    ->whereIn('status', [CaseTaskStatus::Open, CaseTaskStatus::InProgress])
                    ->count()
                : 0,
        ];
    }
}
