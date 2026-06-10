import type {
    Evidence,
    FarmOperationPermissions,
    FarmOption,
} from './farm-operations';

export type FinanceSummary = {
    budgets: number;
    fundingPhases: number;
    expenses: number;
    externalTransfers: number;
    budgetedMinor: number;
    spentMinor: number;
    releasedMinor: number;
    transfersMinor: number;
    budgeted: string;
    spent: string;
    released: string;
    transfers: string;
};

export type FinanceVarianceCategory = {
    categoryId: number;
    categoryName: string;
    budgetedMinor: number;
    spentMinor: number;
    balanceMinor: number;
    varianceMinor: number;
    budgeted: string;
    spent: string;
    balance: string;
    variance: string;
};

export type FinanceVariance = {
    budgetedMinor: number;
    spentMinor: number;
    balanceMinor: number;
    varianceMinor: number;
    budgeted: string;
    spent: string;
    balance: string;
    variance: string;
    byCategory: FinanceVarianceCategory[];
};

export type CarryForwardPhase = {
    phaseId: number;
    phaseName: string;
    openingMinor: number;
    releasedMinor: number;
    spentMinor: number;
    carryForwardMinor: number;
    opening: string;
    released: string;
    spent: string;
    carryForward: string;
};

export type CarryForward = {
    releasedMinor: number;
    spentMinor: number;
    carryForwardMinor: number;
    released: string;
    spent: string;
    carryForward: string;
    phases: CarryForwardPhase[];
};

export type FinanceDashboardSummary = FinanceSummary & {
    variance: FinanceVariance;
    carryForward: CarryForward;
};

export type FinanceFarm = {
    id: number;
    name: string;
    productionCycles: Array<{ id: number; name: string }>;
};

export type ExpenseCategory = {
    id: number;
    name: string;
    slug: string;
};

export type BudgetLine = {
    id: number;
    budgetId: number;
    budgetName: string;
    expenseCategoryId: number | null;
    expenseCategoryName: string | null;
    description: string;
    plannedAmountMinor: number;
    plannedAmount: string;
    currency: string;
};

export type Budget = {
    id: number;
    name: string;
    farmId: number;
    farmName: string;
    productionCycleId: number | null;
    productionCycleName: string | null;
    status: string;
    statusLabel: string;
    currency: string;
    periodStartOn?: string | null;
    periodEndOn?: string | null;
    notes?: string | null;
    lines?: BudgetLine[];
};

export type FundingPhase = {
    id: number;
    name: string;
    farmId: number;
    farmName: string;
    productionCycleId: number | null;
    productionCycleName: string | null;
    budgetId: number | null;
    budgetName: string | null;
    status: string;
    statusLabel: string;
    currency: string;
    milestone?: string | null;
    plannedAmountMinor?: number;
    requestedAmountMinor?: number;
    approvedAmountMinor?: number;
    externallyReleasedAmountMinor?: number;
    plannedAmount?: string;
    requestedAmount?: string;
    approvedAmount?: string;
    externallyReleasedAmount?: string;
    expectedOn?: string | null;
    releasedOn?: string | null;
    notes?: string | null;
};

export type FinanceActivity = {
    id: number;
    farmId: number;
    farmName: string;
    productionCycleId: number | null;
    productionCycleName: string | null;
    activityType: string;
    activityDate: string;
};

export type Expense = {
    id: number;
    farmId: number;
    farmName: string;
    productionCycleId: number | null;
    productionCycleName: string | null;
    budgetId: number | null;
    budgetName: string | null;
    budgetLineId: number | null;
    fundingPhaseId: number | null;
    fundingPhaseName: string | null;
    expenseCategoryId: number;
    expenseCategoryName: string;
    farmActivityId: number | null;
    farmActivityType: string | null;
    amountMinor: number;
    amount: string;
    currency: string;
    status: string;
    statusLabel: string;
    vendor?: string | null;
    paymentMethod?: string | null;
    description?: string;
    notes?: string | null;
    incurredOn?: string;
    receipts?: Evidence[];
};

export type TransferReconciliation = {
    id: number;
    status: string;
    statusLabel: string;
    reconciledAmountMinor: number;
    reconciledAmount: string;
    reconciledAt: string;
    notes: string | null;
};

export type ExternalTransfer = {
    id: number;
    farmId: number;
    farmName: string;
    productionCycleId: number | null;
    productionCycleName: string | null;
    budgetId: number | null;
    budgetName: string | null;
    fundingPhaseId: number | null;
    fundingPhaseName: string | null;
    expenseId: number | null;
    direction: string;
    directionLabel: string;
    transferType: string;
    status: string;
    statusLabel: string;
    counterpartyName: string | null;
    reference: string | null;
    amountMinor: number;
    amount: string;
    currency: string;
    transferredOn: string;
    notes: string | null;
    proof: Evidence[];
    reconciliations: TransferReconciliation[];
};

export type FinanceOptions = {
    budgetStatuses: FarmOption[];
    fundingPhaseStatuses: FarmOption[];
    expenseStatuses: FarmOption[];
    externalTransferDirections: FarmOption[];
    externalTransferStatuses: FarmOption[];
    transferReconciliationStatuses: FarmOption[];
};

export type FinancePermissions = FarmOperationPermissions;
