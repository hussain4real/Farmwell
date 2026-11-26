import type { Evidence, FarmOption } from './farm-operations';
import type { RoleOption, TeamPermissions } from './teams';

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
    farmType: string;
    farmTypeLabel: string;
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
    saleNetMinor: number;
    capitalRecoveredMinor: number;
    unrecoveredCapitalMinor: number;
    investorShareMinor: number;
    farmShareMinor: number;
    released: string;
    spent: string;
    balance: string;
    saleNet: string;
    capitalRecovered: string;
    unrecoveredCapital: string;
    investorShare: string;
    farmShare: string;
};

export type InvestorCycleSnapshot = {
    cycleId: number;
    cycleName: string;
    season: string | null;
    domain: 'poultry' | 'crop' | 'aquaculture' | 'general';
    domainLabel: string;
    farmType: string;
    farmTypeLabel: string;
    status: string;
    statusLabel: string;
    plannedStartOn: string;
    plannedEndOn: string | null;
    ageDays: number;
    progressPercent: number;
    logCount: number;
    lastLoggedOn: string | null;
    stock: {
        initialCount: number | null;
        currentCount: number | null;
        survivalRatePercent: number | null;
        mortalityRatePercent: number | null;
    };
    feeding: {
        totalFeedKg: number;
        averageDailyFeedKg: number;
        latestAverageWeightG: number | null;
        estimatedBiomassKg: number | null;
        fcr: number | null;
    };
    poultry: {
        totalEggs: number;
        totalCrates: number;
        recentLayRatePercent: number | null;
    };
    aquaculture: {
        waterStatus: 'optimal' | 'warning' | 'critical' | 'unknown';
        waterExchangeCount: number;
        latestPh: number | null;
        latestTempC: number | null;
        latestDissolvedOxygen: number | null;
        latestAmmonia: number | null;
    };
    crop: {
        currentGrowthStage: string | null;
        currentGrowthStageLabel: string | null;
        stageProgressPercent: number;
        totalRainfallMm: number;
        irrigationCount: number;
        latestPestPressure: string | null;
        latestPestPressureLabel: string | null;
    };
    series: {
        dates: string[];
        feedKg: number[];
        eggs: number[];
        rainfallMm: number[];
    };
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
    farmName?: string;
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
    expenseDescription: string | null;
    milestone: string | null;
    milestoneEvidence: { id: number; fileName: string; downloadUrl: string }[];
    requesterComment: string | null;
    decisionComment: string | null;
    requestedAt: string;
    decidedAt: string | null;
};

export type InvestorFundingGroup = {
    cycleId: number | null;
    cycleName: string | null;
    season: string | null;
    milestones: {
        id: number;
        name: string;
        milestone: string | null;
        expectedOn: string | null;
        events: {
            id: string;
            label: string;
            detail: string | null;
            date: string | null;
            amount: string | null;
            currency: string;
            files: Pick<Evidence, 'id' | 'fileName' | 'downloadUrl'>[];
        }[];
    }[];
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

export type InvestorHarvestRecord = {
    id: number;
    harvestedOn: string;
    commodityName: string;
    stage: string;
    stageLabel: string;
    quantity: string;
    quantityUnit: string;
    qualityNotes: string | null;
    status: string;
    statusLabel: string;
    evidence: Evidence[];
};

export type InvestorSaleRecord = {
    id: number;
    soldOn: string;
    buyerName: string;
    commodityName: string;
    quantity: string;
    quantityUnit: string;
    grossAmount: string;
    deductionAmount: string;
    netAmount: string;
    currency: string;
    paymentStatus: string;
    paymentStatusLabel: string;
    evidence: Evidence[];
};

export type InvestorDistributionRecord = {
    id: number;
    saleRecordId: number;
    buyerName: string;
    saleNetAmount: string;
    capitalRecovered: string;
    unrecoveredCapital: string;
    netProfit: string;
    investorShare: string;
    farmShare: string;
    currency: string;
    status: string;
    statusLabel: string;
    calculatedAt: string;
};

export type InvestorActivityEvidence = Pick<
    Evidence,
    'id' | 'fileName' | 'caption' | 'downloadUrl'
> & { previewUrl: string | null; thumbnailUrl?: string | null };

export type InvestorActivity = {
    id: number;
    activityDate: string;
    activityType: string;
    summary: string | null;
    status: string;
    evidence: InvestorActivityEvidence[];
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
    authorId?: number;
    authorName: string;
    isInvestor?: boolean;
    body: string;
    subjectLabel: string;
    createdAt: string | null;
};

export type InvestorPortalAgreement = InvestorAgreement & {
    summary: InvestorAgreementSummary;
    cycleSnapshot: InvestorCycleSnapshot | null;
    fundingTimeline: InvestorFundingGroup[];
    expenses: InvestorExpense[];
    externalTransfers: InvestorExternalTransfer[];
    harvestRecords: InvestorHarvestRecord[];
    saleRecords: InvestorSaleRecord[];
    distributionRecords: InvestorDistributionRecord[];
    aiSummaries: {
        id: number;
        text: string;
        html: string;
        publishedAt: string;
    }[];
    activities: InvestorActivity[];
    tasks: InvestorTask[];
    planChanges: InvestorPlanChange[];
    approvalRequests: InvestorApprovalRequest[];
    comments: InvestorComment[];
};

export type InvestorManagementAgreement = InvestorAgreement & {
    comments: InvestorComment[];
    pendingApprovalsCount: number;
};

export type InvestorManagementSummary = {
    investorCount: number;
    activeAgreementCount: number;
    draftAgreementCount: number;
    pendingApprovalCount: number;
    questionCount: number;
    committedByCurrency: Array<{
        currency: string;
        committed: string;
        funded: string;
    }>;
};

export type InvestorInviteContext = {
    availableRoles: RoleOption[];
    callingCode: string | null;
    phoneCountries: Record<string, string> | null;
    phoneLoginEnabled: boolean;
    pendingInvestorInvitations: Array<{
        code: string;
        email: string | null;
        phone: string | null;
        phoneUrl: string | null;
        createdAt: string | null;
    }>;
};

export type InvestorPortfolioSummary = {
    farmCount: number;
    agreementCount: number;
    activeAgreementCount: number;
    pendingDecisionCount: number;
    totalsByCurrency: Array<{
        currency: string;
        committed: string;
        funded: string;
        fundingPercent: number | null;
        released: string;
        spent: string;
        capitalRecovered: string;
        investorShare: string;
        totalReturned: string;
    }>;
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
