<?php

namespace App\Actions\FarmOperations;

use App\Actions\Health\BuildHealthPageData;
use App\Actions\Inventory\BuildInventoryPageData;
use App\Enums\CropGrowthStage;
use App\Enums\EnterpriseSubtype;
use App\Enums\HealthEventStatus;
use App\Enums\HealthEventType;
use App\Enums\PestPressure;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionLogDomain;
use App\Enums\SalePaymentStatus;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\HealthEvent;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\ProductionPlanChange;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use App\Support\HealthScheduleTemplates;

class BuildCycleDetailPageData
{
    public function __construct(
        private CalculateCycleTelemetry $calculateCycleTelemetry,
        private BuildCycleProfitability $buildCycleProfitability,
        private BuildCycleCalculators $buildCycleCalculators,
        private BuildCycleSummaryExport $buildCycleSummaryExport,
        private BuildFarmDetailPageData $buildFarmDetailPageData,
        private BuildHealthPageData $buildHealthPageData,
        private BuildInventoryPageData $buildInventoryPageData,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Team $team, Farm $farm, ProductionCycle $cycle, User $user): array
    {
        $cycle->load(['productionUnits', 'commodities']);
        $canViewFinance = $user->can('viewFinance', $team);

        $logs = $cycle->productionLogs()
            ->with(['productionCycle', 'productionUnit', 'recordedBy', 'inventoryItem', 'media'])
            ->latest('log_date')
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (ProductionLog $log): array => $this->buildFarmDetailPageData->logPayload($log, $team))
            ->values();

        $healthEvents = $cycle->healthEvents()
            ->with(['farm', 'productionCycle', 'productionUnit', 'inventoryItem'])
            ->orderByRaw("CASE WHEN status = 'scheduled' THEN 0 ELSE 1 END")
            ->orderBy('scheduled_on')
            ->get()
            ->map(fn (HealthEvent $event): array => $this->buildHealthPageData->eventPayload($event))
            ->values();

        $activeWithdrawals = $cycle->healthEvents()
            ->where('status', HealthEventStatus::Completed->value)
            ->get()
            ->filter(fn (HealthEvent $event): bool => $event->isUnderWithdrawal())
            ->map(fn (HealthEvent $event): array => $this->buildHealthPageData->eventPayload($event))
            ->values();

        $inventoryMovements = $cycle->inventoryMovements()
            ->with(['inventoryItem', 'farm', 'productionCycle', 'recordedBy'])
            ->latest('movement_date')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (InventoryMovement $movement): array => $this->buildInventoryPageData->movementPayload($movement))
            ->values();

        $planChanges = $cycle->planChanges()
            ->with(['actor'])
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (ProductionPlanChange $change): array => [
                'id' => $change->id,
                'changeTypeLabel' => $change->change_type->label(),
                'reason' => $change->reason,
                'impact' => $change->impact,
                'recordedBy' => $change->actor?->name,
                'createdAt' => $change->created_at?->toDateString(),
            ])->values();

        $farmUnits = $farm->productionUnits()->orderBy('name')->get()->map(fn (ProductionUnit $unit): array => [
            'id' => $unit->id,
            'name' => $unit->name,
            'unitTypeLabel' => $unit->unit_type->label(),
        ])->values();

        $siblingCycles = $farm->productionCycles()
            ->where('id', '!=', $cycle->id)
            ->latest()
            ->get()
            ->map(fn (ProductionCycle $other): array => [
                'id' => $other->id,
                'name' => $other->name,
                'currentStockCount' => $other->current_stock_count,
            ])->values();

        $inventoryItems = $team->inventoryItems()
            ->where(fn ($query) => $query->where('farm_id', $farm->id)->orWhereNull('farm_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item): array => $this->buildInventoryPageData->itemPayload($item))
            ->values();

        $telemetry = $this->calculateCycleTelemetry->handle($cycle);
        $profitability = $canViewFinance ? $this->buildCycleProfitability->handle($cycle) : null;
        $calculators = $this->buildCycleCalculators->handle($cycle, $telemetry, $profitability);
        $summaryWhatsappText = $this->buildCycleSummaryExport->toWhatsappText([
            'farmName' => $farm->name,
            'cycleName' => $cycle->name,
            'farmTypeLabel' => $cycle->farm_type->label(),
            'domain' => (string) $telemetry['domain'],
            'statusLabel' => $cycle->status->label(),
            'telemetry' => $telemetry,
            'profitability' => $profitability,
        ]);

        return [
            'permissions' => $user->toTeamPermissions($team),
            'farm' => [
                'id' => $farm->id,
                'name' => $farm->name,
                'farmType' => $farm->farm_type->value,
                'farmTypeLabel' => $farm->farm_type->label(),
                'units' => $farmUnits,
            ],
            'cycle' => [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'season' => $cycle->season,
                'farmType' => $cycle->farm_type->value,
                'farmTypeLabel' => $cycle->farm_type->label(),
                'domain' => ProductionLogDomain::fromFarmType($cycle->farm_type)->value,
                'enterpriseSubtype' => $cycle->enterprise_subtype?->value,
                'enterpriseSubtypeLabel' => $cycle->enterprise_subtype?->label(),
                'breedOrVariety' => $cycle->breed_or_variety,
                'productionMethod' => $cycle->production_method,
                'plannedStartOn' => $cycle->planned_start_on->toDateString(),
                'plannedEndOn' => $cycle->planned_end_on?->toDateString(),
                'actualStartOn' => $cycle->actual_start_on?->toDateString(),
                'actualEndOn' => $cycle->actual_end_on?->toDateString(),
                'expectedOutputQuantity' => $cycle->expected_output_quantity,
                'expectedOutputUnit' => $cycle->expected_output_unit,
                'initialStockCount' => $cycle->initial_stock_count,
                'currentStockCount' => $cycle->current_stock_count,
                'initialAverageWeightG' => $cycle->initial_average_weight_g,
                'targetWeightG' => $cycle->target_weight_g,
                'currentGrowthStage' => $cycle->current_growth_stage?->value,
                'currentGrowthStageLabel' => $cycle->current_growth_stage?->label(),
                'status' => $cycle->status->value,
                'statusLabel' => $cycle->status->label(),
                'planVersion' => $cycle->plan_version,
                'planSummary' => $cycle->plan_summary,
                'productionUnits' => $cycle->productionUnits->map(fn (ProductionUnit $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'unitTypeLabel' => $unit->unit_type->label(),
                    'size' => $unit->size,
                    'sizeUnit' => $unit->size_unit,
                ])->values(),
                'commodities' => $cycle->commodities->map(fn (Commodity $commodity): array => [
                    'id' => $commodity->id,
                    'name' => $commodity->name,
                    'measurementUnit' => $commodity->measurement_unit,
                ])->values(),
            ],
            'telemetry' => $telemetry,
            'profitability' => $profitability,
            'calculators' => $calculators,
            'summaryWhatsappText' => $summaryWhatsappText,
            'logs' => $logs,
            'healthEvents' => $healthEvents,
            'activeWithdrawals' => $activeWithdrawals,
            'inventoryMovements' => $inventoryMovements,
            'inventoryItems' => $inventoryItems,
            'siblingCycles' => $siblingCycles,
            'planChanges' => $planChanges,
            'options' => [
                'cycleStatuses' => ProductionCycleStatus::options(),
                'enterpriseSubtypes' => EnterpriseSubtype::options(),
                'cropGrowthStages' => CropGrowthStage::options(),
                'pestPressures' => PestPressure::options(),
                'healthEventTypes' => HealthEventType::options(),
                'healthEventStatuses' => HealthEventStatus::options(),
                'healthPresets' => HealthScheduleTemplates::options(),
                'paymentStatuses' => SalePaymentStatus::options(),
            ],
        ];
    }
}
