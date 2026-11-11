<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationRolesController extends Controller
{
    /**
     * Read-only view of what each role can do. Roles are defined by the product so
     * clinical approval authority cannot be granted by accident.
     */
    public function __invoke(Request $request, Team $current_team): Response
    {
        Gate::authorize('manageSettings', $current_team);

        $roles = collect(TeamRole::cases())
            ->reject(fn (TeamRole $role) => $role === TeamRole::Member)
            ->values();

        $groupTitles = [
            'team' => 'Organization',
            'organization' => 'Organization',
            'member' => 'Staff',
            'invitation' => 'Staff',
            'patients' => 'Patients and cases',
            'cases' => 'Patients and cases',
            'documents' => 'Documents and AI',
            'clinical' => 'Clinical',
            'proposals' => 'Coordination',
            'appointments' => 'Coordination',
            'travel' => 'Coordination',
            'communications' => 'Coordination',
            'tasks' => 'Coordination',
            'reports' => 'Reporting',
        ];

        $groups = collect(TeamPermission::cases())
            ->groupBy(fn (TeamPermission $permission) => $groupTitles[Str::before($permission->value, ':')] ?? 'Other')
            ->map(fn ($permissions, string $title) => [
                'title' => $title,
                'permissions' => $permissions->map(fn (TeamPermission $permission) => [
                    'value' => $permission->value,
                    'label' => Str::headline($permission->name),
                    'roles' => $roles
                        ->filter(fn (TeamRole $role) => $role->hasPermission($permission))
                        ->map(fn (TeamRole $role) => $role->value)
                        ->values(),
                ])->values(),
            ])->values();

        return Inertia::render('organizations/roles', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'roles' => $roles->map(fn (TeamRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->values(),
            'groups' => $groups,
            'staffUrl' => "/settings/teams/{$current_team->slug}",
        ]);
    }
}
