import type { TeamPermissions } from './teams';

export type FarmOption = {
    value: string;
    label: string;
};

export type FarmStats = {
    farms: number;
    productionUnits: number;
    productionCycles: number;
    commodities: number;
    activities: number;
    openTasks: number;
    pendingIntakes: number;
};

export type Commodity = {
    id: number;
    name: string;
    farmType: string;
    farmTypeLabel: string;
    measurementUnit: string | null;
};

export type ProductionUnit = {
    id: number;
    name: string;
    unitType: string;
    unitTypeLabel: string;
    size: string | null;
    sizeUnit: string | null;
    status: string;
};

export type CycleCommodity = {
    id: number;
    name: string;
    role: string;
    roleLabel: string;
    expectedOutputQuantity: string | null;
    expectedOutputUnit: string | null;
};

export type ProductionCycle = {
    id: number;
    name: string;
    season: string | null;
    farmType: string;
    farmTypeLabel: string;
    productionMethod: string | null;
    plannedStartOn: string;
    plannedEndOn: string | null;
    expectedOutputQuantity: string | null;
    expectedOutputUnit: string | null;
    status: string;
    statusLabel: string;
    planVersion: number;
    planSummary: string | null;
    productionUnits: Pick<ProductionUnit, 'id' | 'name'>[];
    commodities: CycleCommodity[];
};

export type Farm = {
    id: number;
    name: string;
    farmType: string;
    farmTypeLabel: string;
    location: string | null;
    status: string;
    productionUnitsCount: number;
    productionCyclesCount: number;
    productionUnits: ProductionUnit[];
    productionCycles: ProductionCycle[];
};

export type ProductionPlanChange = {
    id: number;
    cycleId: number;
    cycleName: string;
    farmName: string;
    changeType: string;
    changeTypeLabel: string;
    reason: string;
    impact: string;
    investorAgreementId: number | null;
    investorAgreementTitle: string | null;
    investorVisibilityStatus: string;
    investorVisibilityStatusLabel: string;
    recordedBy: string | null;
    createdAt: string | null;
};

export type FarmInvestorAgreement = {
    id: number;
    title: string;
    investorName: string;
    farmId: number;
    productionCycleId: number | null;
};

export type Evidence = {
    id: number;
    name: string;
    fileName: string;
    mimeType: string | null;
    size: number;
    caption: string | null;
    visibility: string;
    capturedOn: string | null;
    downloadUrl: string;
};

export type FarmActivity = {
    id: number;
    farmId: number;
    farmName: string;
    productionUnitId: number | null;
    productionUnitName: string | null;
    productionCycleId: number | null;
    productionCycleName: string | null;
    commodityId: number | null;
    commodityName: string | null;
    activityDate: string;
    activityType: string;
    description: string;
    inputsUsed: string | null;
    labourUsed: string | null;
    cost: string | null;
    remarks: string | null;
    nextActivity: string | null;
    status: string;
    statusLabel: string;
    investorSafeSummary: string | null;
    recordedBy: string | null;
    evidence: Evidence[];
};

export type FarmTask = {
    id: number;
    farmId: number;
    farmName: string;
    productionUnitName: string | null;
    productionCycleName: string | null;
    assignedTo: string | null;
    title: string;
    activityType: string | null;
    description: string | null;
    plannedFor: string | null;
    dueOn: string;
    reminderAt: string | null;
    status: string;
    statusLabel: string;
    statusReason: string | null;
    investorVisible: boolean;
};

export type WhatsappIntake = {
    id: number;
    farmId: number | null;
    farmName: string | null;
    productionUnitId: number | null;
    productionCycleId: number | null;
    commodityId: number | null;
    sourceMessage: string;
    sourceSender: string | null;
    sourceDate: string | null;
    normalizedActivityDate: string | null;
    normalizedActivityType: string | null;
    normalizedDescription: string | null;
    normalizedCost: string | null;
    normalizedNextActivity: string | null;
    normalizedInvestorSafeSummary: string | null;
    reviewStatus: string;
    reviewStatusLabel: string;
    importedBy: string | null;
    evidence: Evidence[];
};

export type FarmOperationOptions = {
    farmTypes: FarmOption[];
    productionUnitTypes: FarmOption[];
    productionCycleStatuses: FarmOption[];
    commodityRoles: FarmOption[];
    planChangeTypes: FarmOption[];
    investorVisibilityStatuses: FarmOption[];
    activityStatuses: FarmOption[];
    taskStatuses: FarmOption[];
    whatsappIntakeStatuses: FarmOption[];
    evidenceVisibilities: FarmOption[];
};

export type FarmOperationPermissions = TeamPermissions;
