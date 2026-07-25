import type { Evidence, FarmOption } from './farm-operations';
import type { TeamPermissions } from './teams';

export type HarvestSummary = {
    harvestRecords: number;
    saleRecords: number | null;
    distributionRecords: number | null;
    saleNetMinor: number | null;
    capitalRecoveredMinor: number | null;
    unrecoveredCapitalMinor: number | null;
    investorShareMinor: number | null;
    farmShareMinor: number | null;
    saleNet: string | null;
    capitalRecovered: string | null;
    unrecoveredCapital: string | null;
    investorShare: string | null;
    farmShare: string | null;
};

export type HarvestFarm = {
    id: number;
    name: string;
    productionUnits: Array<{ id: number; name: string }>;
    productionCycles: Array<{ id: number; name: string }>;
};

export type HarvestCommodity = {
    id: number;
    name: string;
    measurementUnit: string | null;
};

export type HarvestInvestorAgreement = {
    id: number;
    title: string;
    investorName: string;
    farmId: number;
    productionCycleId: number | null;
    currency: string;
    amountFunded: string;
    investorProfitSharePercentage: number;
    farmProfitSharePercentage: number;
};

export type HarvestRecord = {
    id: number;
    farmId: number;
    farmName: string;
    productionUnitId: number | null;
    productionUnitName: string | null;
    productionCycleId: number | null;
    productionCycleName: string | null;
    commodityId: number;
    commodityName: string;
    investorAgreementId: number | null;
    investorAgreementTitle: string | null;
    harvestedOn: string;
    stage: string;
    stageLabel: string;
    sequenceNumber: number;
    quantity: string;
    soldQuantity: string;
    remainingQuantity: string;
    quantityUnit: string;
    qualityNotes: string | null;
    labourCostMinor: number;
    labourCost: string;
    currency: string;
    status: string;
    statusLabel: string;
    investorVisibilityStatus: string;
    investorVisibilityStatusLabel: string;
    notes: string | null;
    evidence: Evidence[];
};

export type SaleRecord = {
    id: number;
    harvestRecordId: number;
    farmId: number;
    farmName: string;
    productionCycleId: number | null;
    productionCycleName: string | null;
    commodityId: number;
    commodityName: string;
    investorAgreementId: number | null;
    investorAgreementTitle: string | null;
    soldOn: string;
    buyerName: string;
    quantity: string;
    quantityUnit: string;
    unitPriceMinor: number;
    grossAmountMinor: number;
    deductionAmountMinor: number;
    netAmountMinor: number;
    unitPrice: string;
    grossAmount: string;
    deductionAmount: string;
    netAmount: string;
    currency: string;
    paymentStatus: string;
    paymentStatusLabel: string;
    reference: string | null;
    investorVisibilityStatus: string;
    investorVisibilityStatusLabel: string;
    notes: string | null;
    evidence: Evidence[];
    distributionRecordId: number | null;
};

export type DistributionRecord = {
    id: number;
    investorAgreementId: number;
    investorAgreementTitle: string;
    investorName: string;
    saleRecordId: number;
    buyerName: string;
    saleNetAmountMinor: number;
    previousCapitalRecoveredMinor: number;
    capitalRecoveredMinor: number;
    unrecoveredCapitalMinor: number;
    netProfitMinor: number;
    investorShareMinor: number;
    farmShareMinor: number;
    saleNetAmount: string;
    previousCapitalRecovered: string;
    capitalRecovered: string;
    unrecoveredCapital: string;
    netProfit: string;
    investorShare: string;
    farmShare: string;
    investorProfitSharePercentage: number;
    farmProfitSharePercentage: number;
    currency: string;
    status: string;
    statusLabel: string;
    investorVisibilityStatus: string;
    investorVisibilityStatusLabel: string;
    approvalRequestId: number | null;
    calculatedAt: string;
};

export type HarvestOptions = {
    harvestStages: FarmOption[];
    harvestStatuses: FarmOption[];
    salePaymentStatuses: FarmOption[];
    distributionStatuses: FarmOption[];
    investorVisibilityStatuses: FarmOption[];
};

export type HarvestPermissions = TeamPermissions;
