import type { Evidence, FarmOption } from './farm-operations';
import type { TeamPermissions } from './teams';

export type InvestorUser = {
    id: number;
    name: string;
    email: string;
};

export type InvestorFarm = {
    id: number;
    name: string;
    farmType: string;
    productionCycles: Array<{ id: number; name: string }>;
};

export type InvestorExpenseCategory = {
    id: number;
    name: string;
    slug: string;
    requiresInvestorApproval: boolean;
};

export type InvestorAgreement = {
    id: number;
    title: string;
    investorId: number;
    investorName: string;
    investorEmail: string;
    farmId: number;
    farmName: string;
    productionCycleId: number | null;
    productionCycleName: string | null;
    status: string;
    statusLabel: string;
    currency: string;
    amountCommittedMinor: number;
    amountCommitted: string;
    amountFundedMinor: number;
    amountFunded: string;
    capitalRecoveryRule: string;
    capitalRecoveryRuleLabel: string;
    investorProfitSharePercentage: number;
    farmProfitSharePercentage: number;
    fundingModel: string | null;
    roleResponsibilities: string | null;
    publicNotes: string | null;
    internalNotes?: string | null;
    startsOn: string | null;
    endsOn: string | null;
    signedAt: string | null;
    documents: Evidence[];
};

export type InvestorAgreementSummary = {
    releasedMinor: number;
    spentMinor: number;
    balanceMinor: number;
    released: string;
    spent: string;
    balance: string;
};

export type InvestorApprovalSettings = {
    currency: string;
    expenseThresholdMinor: number;
    expenseThreshold: string;
};

export type InvestorApprovalRequest = {
    id: number;
    investorAgreementId: number;
    agreementTitle: string;
    investorName: string;
    requestType: string;
    requestTypeLabel: string;
    triggerType: string;
    triggerTypeLabel: string;
    status: string;
    statusLabel: string;
    requestedAmount: string;
    approvedAmount: string | null;
    currency: string;
    subjectLabel: string;
    requesterComment: string | null;
    decisionComment: string | null;
    requestedAt: string;
    decidedAt: string | null;
};

export type InvestorFundingPhase = {
    id: number;
    name: string;
    milestone: string | null;
    status: string;
    statusLabel: string;
    plannedAmount: string;
    approvedAmount: string;
    externallyReleasedAmount: string;
    expectedOn: string | null;
    releasedOn: string | null;
};

export type InvestorExpense = {
    id: number;
    incurredOn: string;
    categoryName: string;
    description: string;
    amount: string;
    currency: string;
    status: string;
    receipts: Evidence[];
};

export type InvestorExternalTransfer = {
    id: number;
    direction: string;
    transferType: string;
    status: string;
    amount: string;
    currency: string;
    transferredOn: string;
    proof: Evidence[];
};

export type InvestorActivity = {
    id: number;
    activityDate: string;
    activityType: string;
    summary: string | null;
    status: string;
    evidence: Evidence[];
    agreementId: number;
};

export type InvestorTask = {
    id: number;
    title: string;
    activityType: string | null;
    status: string;
    dueOn: string | null;
    nextActivity: string | null;
};

export type InvestorPlanChange = {
    id: number;
    changeType: string;
    reason: string;
    impact: string;
    summary: string | null;
    createdAt: string | null;
};

export type InvestorComment = {
    id: number;
    authorName: string;
    body: string;
    subjectLabel: string;
    createdAt: string | null;
};

export type InvestorPortalAgreement = InvestorAgreement & {
    summary: InvestorAgreementSummary;
    fundingPhases: InvestorFundingPhase[];
    expenses: InvestorExpense[];
    externalTransfers: InvestorExternalTransfer[];
    activities: InvestorActivity[];
    tasks: InvestorTask[];
    planChanges: InvestorPlanChange[];
    approvalRequests: InvestorApprovalRequest[];
    comments: InvestorComment[];
};

export type InvestorOptions = {
    agreementStatuses: FarmOption[];
    capitalRecoveryRules: FarmOption[];
    approvalRequestStatuses: FarmOption[];
    approvalRequestTypes: FarmOption[];
    approvalTriggerTypes: FarmOption[];
    investorVisibilityStatuses: FarmOption[];
};

export type InvestorPermissions = TeamPermissions;
