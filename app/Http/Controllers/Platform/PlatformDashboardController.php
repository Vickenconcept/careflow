<?php

namespace App\Http\Controllers\Platform;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\OrganizationSetting;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $adminEmails = config('careflow.admin_emails', []);

        $organizations = Team::query()
            ->withCount(['members', 'departments', 'patientCases'])
            ->with(['organizationSetting', 'members' => fn ($query) => $query
                ->orderBy('name')
                ->limit(25)])
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'members_count' => $team->members_count,
                'departments_count' => $team->departments_count,
                'cases_count' => $team->patient_cases_count,
                'plan_label' => OrganizationSetting::plans()[$team->organizationSetting?->plan ?? 'trial'] ?? 'Trial',
                'subscription_status' => $team->organizationSetting?->subscription_status ?? 'active',
                'seat_limit' => $team->organizationSetting?->seat_limit,
                'usage' => PlatformOrganizationController::usageFor($team),
                'members' => $team->members->map(function (User $member) {
                    $role = $this->membershipRole($member->pivot->role);

                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'role' => $role->value,
                        'role_label' => $role->label(),
                    ];
                })->values()->all(),
            ]);

        $users = User::query()
            ->with(['teams' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_platform_admin' => $user->isPlatformAdmin(),
                'can_impersonate' => ! $user->isPlatformAdmin(),
                'teams' => $user->teams->map(function (Team $team) {
                    $role = $this->membershipRole($team->pivot->role);

                    return [
                        'id' => $team->id,
                        'name' => $team->name,
                        'slug' => $team->slug,
                        'role' => $role->value,
                        'role_label' => $role->label(),
                    ];
                })->values()->all(),
            ]);

        return Inertia::render('platform/dashboard', [
            'stats' => [
                'organizations' => Team::query()->count(),
                'users' => User::query()->count(),
                'platform_admins' => User::query()
                    ->where(function ($query) use ($adminEmails): void {
                        $query->where('is_platform_admin', true);

                        foreach ($adminEmails as $adminEmail) {
                            $query->orWhereRaw('LOWER(email) = ?', [$adminEmail]);
                        }
                    })
                    ->count(),
                'cases' => PatientCase::query()->count(),
            ],
            'admin_emails' => $adminEmails,
            'organizations' => $organizations,
            'users' => $users,
            'disclaimer' => 'Platform admin access comes from ADMIN_EMAILS and/or users.is_platform_admin. Impersonation is required to enter an organization user interface; it is audited in application logs.',
        ]);
    }

    private function membershipRole(mixed $role): TeamRole
    {
        if ($role instanceof TeamRole) {
            return $role;
        }

        return TeamRole::from((string) $role);
    }
}
