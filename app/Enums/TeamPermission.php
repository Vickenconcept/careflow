<?php

namespace App\Enums;

enum TeamPermission: string
{
    // Team / organization management
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';
    case ManageOrganizationSettings = 'organization:manage-settings';
    case ManageDepartments = 'organization:manage-departments';

    // Staff
    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';
    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    // Patient operations (defined early; enforced when features land)
    case ViewPatients = 'patients:view';
    case ManagePatients = 'patients:manage';
    case ViewCases = 'cases:view';
    case ManageCases = 'cases:manage';
    case AssignCases = 'cases:assign';
    case UpdateCaseStatus = 'cases:update-status';

    // Documents
    case ViewDocuments = 'documents:view';
    case ManageDocuments = 'documents:manage';
    case ReviewAiOutputs = 'documents:review-ai';

    // Clinical
    case ViewClinical = 'clinical:view';
    case ReviewCases = 'clinical:review';
    case ApproveClinical = 'clinical:approve';

    // Coordination & reporting
    case ManageProposals = 'proposals:manage';
    case ManageAppointments = 'appointments:manage';
    case ManageTravel = 'travel:manage';
    case ManageCommunications = 'communications:manage';
    case ManageTasks = 'tasks:manage';
    case ViewReports = 'reports:view';
}
