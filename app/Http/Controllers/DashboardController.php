<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Enums\TeamRole;
use App\Http\Requests\Organizations\FilterDashboardMetricsRequest;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\TeamInvitation;
use App\Queries\OrganizationDashboardMetricsQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        FilterDashboardMetricsRequest $request,
        OrganizationDashboardMetricsQuery $metricsQuery,
    ): Response|RedirectResponse {
        $email = strtolower($request->user()->email);
        $team = $request->user()->currentTeam;
        $filters = $request->filters();

        $role = $team !== null ? $request->user()->teamRole($team) : null;

        if ($team !== null && in_array($role, [TeamRole::Doctor, TeamRole::Nurse], true)) {
            return to_route('organizations.doctor-queue', ['current_team' => $team->slug]);
        }

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $filterOptions = [
            'statuses' => collect(CaseStatus::cases())->map(fn (CaseStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values()->all(),
            'departments' => [],
            'countries' => [],
            'languages' => config('careflow.portal.supported_languages'),
            'coordinators' => [],
            'doctors' => [],
        ];

        if ($team !== null) {
            $filterOptions['departments'] = $team->departments()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])
                ->all();

            $filterOptions['countries'] = Patient::query()
                ->where('team_id', $team->id)
                ->whereNotNull('country_of_residence')
                ->distinct()
                ->orderBy('country_of_residence')
                ->pluck('country_of_residence')
                ->values()
                ->all();

            $members = Membership::query()
                ->where('team_id', $team->id)
                ->with('user:id,name')
                ->get();

            $filterOptions['coordinators'] = $members
                ->filter(fn (Membership $membership) => in_array($membership->role, [
                    TeamRole::Owner,
                    TeamRole::Admin,
                    TeamRole::Coordinator,
                ], true))
                ->map(fn (Membership $membership) => [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                ])
                ->unique('id')
                ->values()
                ->all();

            $filterOptions['doctors'] = $members
                ->filter(fn (Membership $membership) => in_array($membership->role, [
                    TeamRole::Doctor,
                    TeamRole::Owner,
                    TeamRole::Admin,
                ], true))
                ->map(fn (Membership $membership) => [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                ])
                ->unique('id')
                ->values()
                ->all();
        }

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'metrics' => $metricsQuery->for($request->user(), $team, $filters),
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'team' => $team ? [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
            ] : null,
        ]);
    }
}
