export type TeamRole = 'owner' | 'admin' | 'member' | 'investor';

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

export type TeamPermissions = {
    canUpdateTeam: boolean;
    canDeleteTeam: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
    canManageSettings: boolean;
    canViewAuditEvents: boolean;
    canViewFarmOperations: boolean;
    canManageFarmOperations: boolean;
    canViewFinance: boolean;
    canManageFinance: boolean;
    canViewInvestorPortal: boolean;
    canViewInvestorAgreements: boolean;
    canManageInvestorAgreements: boolean;
    canViewApprovalRequests: boolean;
    canManageApprovalRequests: boolean;
};

export type RoleOption = {
    value: TeamRole;
    label: string;
};
