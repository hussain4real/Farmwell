<?php

namespace App\Support;

readonly class TeamPermissions
{
    public function __construct(
        public bool $canUpdateTeam,
        public bool $canDeleteTeam,
        public bool $canAddMember,
        public bool $canUpdateMember,
        public bool $canRemoveMember,
        public bool $canCreateInvitation,
        public bool $canCancelInvitation,
        public bool $canManageSettings,
        public bool $canViewAuditEvents,
        public bool $canViewFarmOperations,
        public bool $canManageFarmOperations,
        public bool $canViewFinance,
        public bool $canManageFinance,
        public bool $canViewInvestorPortal,
        public bool $canViewInvestorAgreements,
        public bool $canManageInvestorAgreements,
        public bool $canViewApprovalRequests,
        public bool $canManageApprovalRequests,
    ) {
        //
    }
}
