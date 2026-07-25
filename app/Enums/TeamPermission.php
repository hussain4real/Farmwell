<?php

namespace App\Enums;

enum TeamPermission: string
{
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    case ManageSettings = 'settings:manage';
    case ViewAuditEvents = 'audit-events:view';

    case ViewFarmOperations = 'farm-operations:view';
    case ManageFarmOperations = 'farm-operations:manage';

    case ViewFinance = 'finance:view';
    case ManageFinance = 'finance:manage';

    case ViewInvestorPortal = 'investor-portal:view';
    case ViewInvestorAgreements = 'investor-agreements:view';
    case ManageInvestorAgreements = 'investor-agreements:manage';
    case ViewApprovalRequests = 'approval-requests:view';
    case ManageApprovalRequests = 'approval-requests:manage';
}
