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
    recordedBy: string | null;
    createdAt: string | null;
};

export type FarmOperationOptions = {
    farmTypes: FarmOption[];
    productionUnitTypes: FarmOption[];
    productionCycleStatuses: FarmOption[];
    commodityRoles: FarmOption[];
    planChangeTypes: FarmOption[];
};

export type FarmOperationPermissions = TeamPermissions;
