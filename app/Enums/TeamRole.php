<?php

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Coordinator = 'coordinator';
    case Doctor = 'doctor';
    case Nurse = 'nurse';
    case ManagementViewer = 'management_viewer';
    case Member = 'member';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Hospital Administrator',
            self::Coordinator => 'International Patient Coordinator',
            self::Doctor => 'Doctor',
            self::Nurse => 'Nurse / Clinical Reviewer',
            self::ManagementViewer => 'Management Viewer',
            self::Member => 'Member',
        };
    }

    /**
     * Get all the permissions for this role.
     *
     * @return array<TeamPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => TeamPermission::cases(),
            self::Admin => [
                TeamPermission::UpdateTeam,
                TeamPermission::ManageOrganizationSettings,
                TeamPermission::ManageDepartments,
                TeamPermission::AddMember,
                TeamPermission::UpdateMember,
                TeamPermission::RemoveMember,
                TeamPermission::CreateInvitation,
                TeamPermission::CancelInvitation,
                TeamPermission::ViewPatients,
                TeamPermission::ManagePatients,
                TeamPermission::ViewCases,
                TeamPermission::ManageCases,
                TeamPermission::AssignCases,
                TeamPermission::UpdateCaseStatus,
                TeamPermission::ViewDocuments,
                TeamPermission::ManageDocuments,
                TeamPermission::ReviewAiOutputs,
                TeamPermission::ManageProposals,
                TeamPermission::ManageAppointments,
                TeamPermission::ManageTravel,
                TeamPermission::ManageCommunications,
                TeamPermission::ManageTasks,
                TeamPermission::ViewReports,
            ],
            self::Coordinator => [
                TeamPermission::ViewPatients,
                TeamPermission::ManagePatients,
                TeamPermission::ViewCases,
                TeamPermission::ManageCases,
                TeamPermission::AssignCases,
                TeamPermission::UpdateCaseStatus,
                TeamPermission::ViewDocuments,
                TeamPermission::ManageDocuments,
                TeamPermission::ReviewAiOutputs,
                TeamPermission::ManageProposals,
                TeamPermission::ManageAppointments,
                TeamPermission::ManageTravel,
                TeamPermission::ManageCommunications,
                TeamPermission::ManageTasks,
            ],
            self::Doctor => [
                TeamPermission::ViewPatients,
                TeamPermission::ViewCases,
                TeamPermission::ViewDocuments,
                TeamPermission::ReviewAiOutputs,
                TeamPermission::ViewClinical,
                TeamPermission::ReviewCases,
                TeamPermission::ApproveClinical,
                TeamPermission::ManageTasks,
            ],
            self::Nurse => [
                TeamPermission::ViewPatients,
                TeamPermission::ViewCases,
                TeamPermission::ViewDocuments,
                TeamPermission::ReviewAiOutputs,
                TeamPermission::ViewClinical,
                TeamPermission::ReviewCases,
                TeamPermission::ManageTasks,
            ],
            self::ManagementViewer => [
                TeamPermission::ViewReports,
            ],
            self::Member => [],
        };
    }

    /**
     * Determine if the role has the given permission.
     */
    public function hasPermission(TeamPermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Get the hierarchy level for this role.
     * Higher numbers indicate higher privileges for coarse middleware checks.
     */
    public function level(): int
    {
        return match ($this) {
            self::Owner => 100,
            self::Admin => 90,
            self::Coordinator => 50,
            self::Doctor => 50,
            self::Nurse => 40,
            self::ManagementViewer => 20,
            self::Member => 10,
        };
    }

    /**
     * Check if this role is at least as privileged as another role.
     */
    public function isAtLeast(TeamRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Get the roles that can be assigned to team members (excludes Owner).
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::Owner)
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
