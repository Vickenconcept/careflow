export type TeamRole =
    | 'owner'
    | 'admin'
    | 'coordinator'
    | 'doctor'
    | 'nurse'
    | 'management_viewer'
    | 'member';

export type Team = {
    id: number;
    name: string;
    slug: string;
    isPersonal: boolean;
    role?: TeamRole;
    roleLabel?: string;
    isCurrent?: boolean;
};

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: TeamRole;
    role_label: string;
};

export type TeamInvitation = {
    code: string;
    email: string;
    role: TeamRole;
    role_label: string;
    created_at: string;
};

export type TeamInvitationContext = {
    code: string;
    teamName: string;
};

export type DashboardInvitation = {
    code: string;
    inviterName: string;
    team: {
        name: string;
        slug: string;
    };
};

export type TeamPermissions = {
    canUpdateTeam: boolean;
    canDeleteTeam: boolean;
    canManageOrganizationSettings: boolean;
    canManageDepartments: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
    canViewPatients: boolean;
    canManagePatients: boolean;
    canViewCases: boolean;
    canManageCases: boolean;
    canAssignCases: boolean;
    canUpdateCaseStatus: boolean;
    canViewDocuments: boolean;
    canManageDocuments: boolean;
    canReviewAiOutputs: boolean;
    canViewClinical: boolean;
    canReviewCases: boolean;
    canApproveClinical: boolean;
    canManageCommunications: boolean;
    canManageTasks: boolean;
    canViewReports: boolean;
};

export type RoleOption = {
    value: TeamRole;
    label: string;
};

export type OrganizationSettings = {
    legal_name: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country: string | null;
    timezone: string;
    default_currency: string;
    default_language: string;
    supported_languages: string[];
    brand_primary_color: string | null;
    brand_secondary_color: string | null;
    ai_processing_enabled: boolean;
    ai_provider: string;
};

export type Department = {
    id: number;
    name: string;
    slug: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    sort_order: number;
};
