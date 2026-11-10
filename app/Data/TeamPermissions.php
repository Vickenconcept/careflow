<?php

namespace App\Data;

readonly class TeamPermissions
{
    public function __construct(
        public bool $canUpdateTeam,
        public bool $canDeleteTeam,
        public bool $canManageOrganizationSettings,
        public bool $canManageDepartments,
        public bool $canAddMember,
        public bool $canUpdateMember,
        public bool $canRemoveMember,
        public bool $canCreateInvitation,
        public bool $canCancelInvitation,
        public bool $canViewPatients,
        public bool $canManagePatients,
        public bool $canViewCases,
        public bool $canManageCases,
        public bool $canAssignCases,
        public bool $canUpdateCaseStatus,
        public bool $canViewDocuments,
        public bool $canManageDocuments,
        public bool $canReviewAiOutputs,
        public bool $canViewClinical,
        public bool $canReviewCases,
        public bool $canApproveClinical,
        public bool $canManageCommunications,
        public bool $canManageTasks,
        public bool $canViewReports,
    ) {
        //
    }
}
