<?php

namespace App\Actions\Harvests;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Enums\DistributionStatus;
use App\Enums\HarvestRecordStatus;
use App\Enums\HarvestStage;
use App\Enums\InvestorAgreementStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\SalePaymentStatus;
use App\Models\Commodity;
use App\Models\DistributionRecord;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\InvestorAgreement;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BuildHarvestPageData
{
    public function __construct(private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Team $team, User $user): array
    {
        $canViewFinance = $user->can('viewFinance', $team);

        return [
            'permissions' => $user->toTeamPermissions($team),
            'currency' => $this->resolveTeamFinanceCurrency->handle($team),
            'summary' => $canViewFinance ? $this->summary($team) : $this->operationalSummary($team),
            'farms' => $this->farms($team)->map(fn (Farm $farm) => $this->farmPayload($farm)),
            'commodities' => $this->commodities($team)->map(fn (Commodity $commodity) => $this->commodityPayload($commodity)),
            'investorAgreements' => $canViewFinance
                ? $this->investorAgreements($team)->map(fn (InvestorAgreement $agreement) => $this->investorAgreementPayload($agreement))
                : [],
            'harvestRecords' => $this->harvestRecords($team)->map(fn (HarvestRecord $harvest) => $this->harvestPayload($harvest, $team, $canViewFinance)),
            'saleRecords' => $canViewFinance
                ? $this->saleRecords($team)->map(fn (SaleRecord $sale) => $this->salePayload($sale, $team))
                : [],
            'distributionRecords' => $canViewFinance
                ? $this->distributionRecords($team)->map(fn (DistributionRecord $distribution) => $this->distributionPayload($distribution))
                : [],
            'options' => $this->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardSummary(Team $team): array
    {
        return $this->summary($team);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Team $team): array
    {
        $saleNetMinor = (int) $team->saleRecords()->sum('net_amount_minor');
        $capitalRecoveredMinor = (int) $team->distributionRecords()->sum('capital_recovered_minor');
        $investorShareMinor = (int) $team->distributionRecords()->sum('investor_share_minor');
        $farmShareMinor = (int) $team->distributionRecords()->sum('farm_share_minor');
        $unrecoveredCapitalMinor = (int) $team->investorAgreements()
            ->withSum('distributionRecords', 'capital_recovered_minor')
            ->get()
            ->sum(function (InvestorAgreement $agreement): int {
                $capitalRecoveredMinor = (int) $agreement->getAttribute('distribution_records_sum_capital_recovered_minor');

                return max(0, $agreement->amount_funded_minor - $capitalRecoveredMinor);
            });

        return [
            'harvestRecords' => $team->harvestRecords()->count(),
            'saleRecords' => $team->saleRecords()->count(),
            'distributionRecords' => $team->distributionRecords()->count(),
            'saleNetMinor' => $saleNetMinor,
            'capitalRecoveredMinor' => $capitalRecoveredMinor,
            'unrecoveredCapitalMinor' => $unrecoveredCapitalMinor,
            'investorShareMinor' => $investorShareMinor,
            'farmShareMinor' => $farmShareMinor,
            'saleNet' => Money::toDecimal($saleNetMinor),
            'capitalRecovered' => Money::toDecimal($capitalRecoveredMinor),
            'unrecoveredCapital' => Money::toDecimal($unrecoveredCapitalMinor),
            'investorShare' => Money::toDecimal($investorShareMinor),
            'farmShare' => Money::toDecimal($farmShareMinor),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operationalSummary(Team $team): array
    {
        return [
            'harvestRecords' => $team->harvestRecords()->count(),
            'saleRecords' => null,
            'distributionRecords' => null,
            'saleNetMinor' => null,
            'capitalRecoveredMinor' => null,
            'unrecoveredCapitalMinor' => null,
            'investorShareMinor' => null,
            'farmShareMinor' => null,
            'saleNet' => null,
            'capitalRecovered' => null,
            'unrecoveredCapital' => null,
            'investorShare' => null,
            'farmShare' => null,
        ];
    }

    /**
     * @return Collection<int, Farm>
     */
    private function farms(Team $team): Collection
    {
        return $team->farms()
            ->with(['productionUnits', 'productionCycles' => fn ($query) => $query->latest()])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Commodity>
     */
    private function commodities(Team $team): Collection
    {
        return $team->commodities()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, InvestorAgreement>
     */
    private function investorAgreements(Team $team): Collection
    {
        return $team->investorAgreements()
            ->with('investor')
            ->whereIn('status', [InvestorAgreementStatus::Draft->value, InvestorAgreementStatus::Active->value, InvestorAgreementStatus::Completed->value])
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, HarvestRecord>
     */
    private function harvestRecords(Team $team): Collection
    {
        return $team->harvestRecords()
            ->with(['farm', 'productionUnit', 'productionCycle', 'commodity', 'investorAgreement.investor', 'sales', 'media'])
            ->latest('harvested_on')
            ->latest()
            ->limit(30)
            ->get();
    }

    /**
     * @return Collection<int, SaleRecord>
     */
    private function saleRecords(Team $team): Collection
    {
        return $team->saleRecords()
            ->with(['farm', 'productionCycle', 'harvestRecord', 'commodity', 'investorAgreement.investor', 'distributionRecord', 'media'])
            ->latest('sold_on')
            ->latest()
            ->limit(30)
            ->get();
    }

    /**
     * @return Collection<int, DistributionRecord>
     */
    private function distributionRecords(Team $team): Collection
    {
        return $team->distributionRecords()
            ->with(['investorAgreement.investor', 'saleRecord'])
            ->latest('calculated_at')
            ->limit(30)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function farmPayload(Farm $farm): array
    {
        return [
            'id' => $farm->id,
            'name' => $farm->name,
            'productionUnits' => $farm->productionUnits->map(fn ($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
            ]),
            'productionCycles' => $farm->productionCycles->map(fn ($cycle) => [
                'id' => $cycle->id,
                'name' => $cycle->name,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commodityPayload(Commodity $commodity): array
    {
        return [
            'id' => $commodity->id,
            'name' => $commodity->name,
            'measurementUnit' => $commodity->measurement_unit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function investorAgreementPayload(InvestorAgreement $agreement): array
    {
        return [
            'id' => $agreement->id,
            'title' => $agreement->title,
            'investorName' => $agreement->investor->name,
            'farmId' => $agreement->farm_id,
            'productionCycleId' => $agreement->production_cycle_id,
            'currency' => $agreement->currency,
            'amountFunded' => Money::toDecimal($agreement->amount_funded_minor),
            'investorProfitSharePercentage' => $agreement->investor_profit_share_percentage,
            'farmProfitSharePercentage' => $agreement->farm_profit_share_percentage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function harvestPayload(HarvestRecord $harvest, Team $team, bool $includeFinance): array
    {
        $soldQuantity = (float) $harvest->sales->sum(fn (SaleRecord $sale): float => (float) $sale->quantity);
        $remainingQuantity = max(0, (float) $harvest->quantity - $soldQuantity);

        return [
            'id' => $harvest->id,
            'farmId' => $harvest->farm_id,
            'farmName' => $harvest->farm->name,
            'productionUnitId' => $harvest->production_unit_id,
            'productionUnitName' => $harvest->productionUnit?->name,
            'productionCycleId' => $harvest->production_cycle_id,
            'productionCycleName' => $harvest->productionCycle?->name,
            'commodityId' => $harvest->commodity_id,
            'commodityName' => $harvest->commodity->name,
            'investorAgreementId' => $includeFinance ? $harvest->investor_agreement_id : null,
            'investorAgreementTitle' => $includeFinance ? $harvest->investorAgreement?->title : null,
            'harvestedOn' => $harvest->harvested_on->toDateString(),
            'stage' => $harvest->stage->value,
            'stageLabel' => $harvest->stage->label(),
            'sequenceNumber' => $harvest->sequence_number,
            'quantity' => $harvest->quantity,
            'soldQuantity' => number_format($soldQuantity, 2, '.', ''),
            'remainingQuantity' => number_format($remainingQuantity, 2, '.', ''),
            'quantityUnit' => $harvest->quantity_unit,
            'qualityNotes' => $harvest->quality_notes,
            'labourCostMinor' => $harvest->labour_cost_minor,
            'labourCost' => Money::toDecimal($harvest->labour_cost_minor),
            'currency' => $harvest->currency,
            'status' => $harvest->status->value,
            'statusLabel' => $harvest->status->label(),
            'investorVisibilityStatus' => $harvest->investor_visibility_status->value,
            'investorVisibilityStatusLabel' => $harvest->investor_visibility_status->label(),
            'notes' => $harvest->notes,
            'evidence' => $harvest->media->map(fn (Media $media) => $this->mediaPayload($media, $team, 'farm-evidence.show')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salePayload(SaleRecord $sale, Team $team): array
    {
        return [
            'id' => $sale->id,
            'harvestRecordId' => $sale->harvest_record_id,
            'farmId' => $sale->farm_id,
            'farmName' => $sale->farm->name,
            'productionCycleId' => $sale->production_cycle_id,
            'productionCycleName' => $sale->productionCycle?->name,
            'commodityId' => $sale->commodity_id,
            'commodityName' => $sale->commodity->name,
            'investorAgreementId' => $sale->investor_agreement_id,
            'investorAgreementTitle' => $sale->investorAgreement?->title,
            'soldOn' => $sale->sold_on->toDateString(),
            'buyerName' => $sale->buyer_name,
            'quantity' => $sale->quantity,
            'quantityUnit' => $sale->quantity_unit,
            'unitPriceMinor' => $sale->unit_price_minor,
            'grossAmountMinor' => $sale->gross_amount_minor,
            'deductionAmountMinor' => $sale->deduction_amount_minor,
            'netAmountMinor' => $sale->net_amount_minor,
            'unitPrice' => Money::toDecimal($sale->unit_price_minor),
            'grossAmount' => Money::toDecimal($sale->gross_amount_minor),
            'deductionAmount' => Money::toDecimal($sale->deduction_amount_minor),
            'netAmount' => Money::toDecimal($sale->net_amount_minor),
            'currency' => $sale->currency,
            'paymentStatus' => $sale->payment_status->value,
            'paymentStatusLabel' => $sale->payment_status->label(),
            'reference' => $sale->reference,
            'investorVisibilityStatus' => $sale->investor_visibility_status->value,
            'investorVisibilityStatusLabel' => $sale->investor_visibility_status->label(),
            'notes' => $sale->notes,
            'evidence' => $sale->media->map(fn (Media $media) => $this->mediaPayload($media, $team, 'finance.evidence.show')),
            'distributionRecordId' => $sale->distributionRecord?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function distributionPayload(DistributionRecord $distribution): array
    {
        return [
            'id' => $distribution->id,
            'investorAgreementId' => $distribution->investor_agreement_id,
            'investorAgreementTitle' => $distribution->investorAgreement->title,
            'investorName' => $distribution->investorAgreement->investor->name,
            'saleRecordId' => $distribution->sale_record_id,
            'buyerName' => $distribution->saleRecord->buyer_name,
            'saleNetAmountMinor' => $distribution->sale_net_amount_minor,
            'previousCapitalRecoveredMinor' => $distribution->previous_capital_recovered_minor,
            'capitalRecoveredMinor' => $distribution->capital_recovered_minor,
            'unrecoveredCapitalMinor' => $distribution->unrecovered_capital_minor,
            'netProfitMinor' => $distribution->net_profit_minor,
            'investorShareMinor' => $distribution->investor_share_minor,
            'farmShareMinor' => $distribution->farm_share_minor,
            'saleNetAmount' => Money::toDecimal($distribution->sale_net_amount_minor),
            'previousCapitalRecovered' => Money::toDecimal($distribution->previous_capital_recovered_minor),
            'capitalRecovered' => Money::toDecimal($distribution->capital_recovered_minor),
            'unrecoveredCapital' => Money::toDecimal($distribution->unrecovered_capital_minor),
            'netProfit' => Money::toDecimal($distribution->net_profit_minor),
            'investorShare' => Money::toDecimal($distribution->investor_share_minor),
            'farmShare' => Money::toDecimal($distribution->farm_share_minor),
            'investorProfitSharePercentage' => $distribution->investor_profit_share_percentage,
            'farmProfitSharePercentage' => $distribution->farm_profit_share_percentage,
            'currency' => $distribution->currency,
            'status' => $distribution->status->value,
            'statusLabel' => $distribution->status->label(),
            'investorVisibilityStatus' => $distribution->investor_visibility_status->value,
            'investorVisibilityStatusLabel' => $distribution->investor_visibility_status->label(),
            'approvalRequestId' => $distribution->approval_request_id,
            'calculatedAt' => $distribution->calculated_at->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaPayload(Media $media, Team $team, string $routeName): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'fileName' => $media->file_name,
            'mimeType' => $media->mime_type,
            'size' => $media->size,
            'caption' => $media->getCustomProperty('caption'),
            'downloadUrl' => route($routeName, [$team, $media]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'harvestStages' => HarvestStage::options(),
            'harvestStatuses' => HarvestRecordStatus::options(),
            'salePaymentStatuses' => SalePaymentStatus::options(),
            'distributionStatuses' => DistributionStatus::options(),
            'investorVisibilityStatuses' => InvestorVisibilityStatus::options(),
        ];
    }
}
