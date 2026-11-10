<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\User;

class PatientCasePolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team)
            && $user->hasTeamPermission($team, TeamPermission::ViewCases);
    }

    public function view(User $user, PatientCase $case): bool
    {
        if (! $user->belongsToTeam($case->team)) {
            return false;
        }

        if (! $user->hasTeamPermission($case->team, TeamPermission::ViewCases)) {
            return false;
        }

        $role = $user->teamRole($case->team);

        if ($role === null) {
            return false;
        }

        if (in_array($role->value, ['owner', 'admin', 'coordinator'], true)) {
            return true;
        }

        if ($role->value === 'doctor') {
            return $case->assigned_doctor_id === $user->id;
        }

        if ($role->value === 'nurse') {
            return $case->assigned_doctor_id === $user->id
                || $case->assigned_coordinator_id === $user->id;
        }

        return false;
    }

    public function manage(User $user, PatientCase $case): bool
    {
        return $user->belongsToTeam($case->team)
            && $user->hasTeamPermission($case->team, TeamPermission::ManageCases);
    }

    public function assign(User $user, PatientCase $case): bool
    {
        return $user->belongsToTeam($case->team)
            && $user->hasTeamPermission($case->team, TeamPermission::AssignCases);
    }

    public function updateStatus(User $user, PatientCase $case): bool
    {
        return $user->belongsToTeam($case->team)
            && $user->hasTeamPermission($case->team, TeamPermission::UpdateCaseStatus);
    }

    public function reviewClinical(User $user, PatientCase $case): bool
    {
        return $this->view($user, $case)
            && $user->hasTeamPermission($case->team, TeamPermission::ReviewCases);
    }

    public function approveClinical(User $user, PatientCase $case): bool
    {
        return $this->view($user, $case)
            && $user->hasTeamPermission($case->team, TeamPermission::ApproveClinical);
    }

    public function viewClinical(User $user, PatientCase $case): bool
    {
        if (! $this->view($user, $case)) {
            return false;
        }

        return $user->hasTeamPermission($case->team, TeamPermission::ViewClinical)
            || $user->hasTeamPermission($case->team, TeamPermission::ReviewCases)
            || $user->hasTeamPermission($case->team, TeamPermission::ApproveClinical)
            || $user->hasTeamPermission($case->team, TeamPermission::ManageCases);
    }

    public function manageProposals(User $user, PatientCase $case): bool
    {
        return $this->view($user, $case)
            && $user->hasTeamPermission($case->team, TeamPermission::ManageProposals);
    }

    public function approveProposals(User $user, PatientCase $case): bool
    {
        return $this->view($user, $case)
            && $user->hasTeamPermission($case->team, TeamPermission::ApproveClinical);
    }

    public function manageAppointments(User $user, PatientCase $case): bool
    {
        return $this->view($user, $case)
            && $user->hasTeamPermission($case->team, TeamPermission::ManageAppointments);
    }

    public function manageTravel(User $user, PatientCase $case): bool
    {
        return $this->view($user, $case)
            && $user->hasTeamPermission($case->team, TeamPermission::ManageTravel);
    }

    public function manageTasks(User $user, PatientCase $case): bool
    {
        return $this->view($user, $case)
            && $user->hasTeamPermission($case->team, TeamPermission::ManageTasks);
    }

    public function manageCommunications(User $user, PatientCase $case): bool
    {
        return $this->view($user, $case)
            && $user->hasTeamPermission($case->team, TeamPermission::ManageCommunications);
    }
}
