<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;

class DepartmentPolicy
{
    /**
     * Determine whether the user can view any departments for the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the department.
     */
    public function view(User $user, Department $department): bool
    {
        return $user->belongsToTeam($department->team);
    }

    /**
     * Determine whether the user can create departments.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageDepartments);
    }

    /**
     * Determine whether the user can update the department.
     */
    public function update(User $user, Department $department): bool
    {
        return $user->hasTeamPermission($department->team, TeamPermission::ManageDepartments);
    }

    /**
     * Determine whether the user can delete the department.
     */
    public function delete(User $user, Department $department): bool
    {
        return $user->hasTeamPermission($department->team, TeamPermission::ManageDepartments);
    }
}
