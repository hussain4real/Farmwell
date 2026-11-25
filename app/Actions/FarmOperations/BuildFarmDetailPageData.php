<?php

namespace App\Actions\FarmOperations;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Actions\Health\BuildHealthPageData;
use App\Actions\Inventory\BuildInventoryPageData;
use App\Enums\CommodityRole;
use App\Enums\CropGrowthStage;
use App\Enums\EnterpriseSubtype;
use App\Enums\ExpenseStatus;
use App\Enums\FarmType;
use App\Enums\HealthEventStatus;
use App\Enums\PestPressure;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionLogDomain;
use App\Enums\ProductionUnitType;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\HealthEvent;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BuildFarmDetailPageData
{
    public function __construct(
        private CalculateCycleTelemetry $calculateCycleTelemetry,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
        private BuildInventoryPageData $buildInventoryPageData,
        private BuildHealthPageData $buildHealthPageData,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Team $team, Farm $farm, User $user): array
    {
        $farm->load([
            'productionUnits' => fn ($query) => $query->orderBy('name'),
            'productionCycles' => fn ($query) => $query->with(['productionUnits', 'commodities'])->latest(),
        ]);

        $currency = $this->resolveTeamFinanceCurrency->handle($team);
        $canViewFinance = $user->can('viewFinance', $team);

        $cycles = $farm->productionCycles->map(function (ProductionCycle $cycle): array {
            $telemetry = $this->calculateCycleTelemetry->handle($cycle);

            return [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'season' => $cycle->season,
                'farmType' => $cycle->farm_type->value,
                'farmTypeLabel' => $cycle->farm_type->label(),
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
                ])->values(),
                'commodities' => $cycle->commodities->map(function (Commodity $commodity): array {
                    $pivot = $commodity->getRelationValue('pivot');
                    assert($pivot instanceof Pivot);

                    return [
                        'id' => $commodity->id,
                        'name' => $commodity->name,
                        'role' => (string) $pivot->getAttribute('role'),
                    ];
                })->values(),
                'telemetry' => $telemetry,
            ];
        })->values();

        $recentLogs = $farm->productionLogs()
            ->with(['productionCycle', 'productionUnit', 'recordedBy', 'inventoryItem', 'media'])
            ->latest('log_date')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (ProductionLog $log): array => $this->logPayload($log, $team))
            ->values();

        $recentActivities = $farm->farmActivities()
            ->with(['productionUnit', 'productionCycle', 'commodity', 'recordedBy'])
            ->latest('activity_date')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (FarmActivity $activity): array => [
                'id' => $activity->id,
                'activityDate' => $activity->activity_date->toDateString(),
                'activityType' => $activity->activity_type,
                'description' => $activity->description,
                'unitName' => $activity->productionUnit?->name,
                'cycleName' => $activity->productionCycle?->name,
                'statusLabel' => $activity->status->label(),
                'recordedBy' => $activity->recordedBy?->name,
            ])->values();

        $openTasks = $farm->farmTasks()
            ->with(['productionUnit', 'productionCycle'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('due_on')
            ->limit(15)
            ->get()
            ->map(fn (FarmTask $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'dueOn' => $task->due_on->toDateString(),
                'status' => $task->status->value,
                'statusLabel' => $task->status->label(),
                'unitName' => $task->productionUnit?->name,
                'cycleName' => $task->productionCycle?->name,
            ])->values();

        $healthEvents = $farm->healthEvents()
            ->with(['productionCycle', 'productionUnit', 'inventoryItem'])
            ->orderByRaw("CASE WHEN status = 'scheduled' THEN 0 ELSE 1 END")
            ->orderBy('scheduled_on')
            ->limit(25)
            ->get()
            ->map(fn (HealthEvent $event): array => $this->buildHealthPageData->eventPayload($event))
            ->values();

        $inventoryItems = $team->inventoryItems()
            ->where(fn ($query) => $query->where('farm_id', $farm->id)->orWhereNull('farm_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item): array => $this->buildInventoryPageData->itemPayload($item))
            ->values();

        $spentMinor = $canViewFinance
            ? (int) $farm->expenses()->where('currency', $currency)->whereIn('status', ExpenseStatus::spendableValues())->sum('amount_minor')
            : null;
        $salesMinor = $canViewFinance
            ? (int) $farm->saleRecords()->where('currency', $currency)->sum('net_amount_minor')
            : null;

        return [
            'permissions' => $user->toTeamPermissions($team),
            'currency' => $currency,
            'farm' => [
                'id' => $farm->id,
                'name' => $farm->name,
                'farmType' => $farm->farm_type->value,
                'farmTypeLabel' => $farm->farm_type->label(),
                'domain' => ProductionLogDomain::fromFarmType($farm->farm_type)->value,
                'location' => $farm->location,
                'status' => $farm->status,
                'registrationNumber' => $farm->registration_number,
                'contactName' => $farm->contact_name,
                'contactPhone' => $farm->contact_phone,
                'description' => $farm->description,
                'productionUnits' => $farm->productionUnits->map(fn (ProductionUnit $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'unitType' => $unit->unit_type->value,
                    'unitTypeLabel' => $unit->unit_type->label(),
                    'size' => $unit->size,
                    'sizeUnit' => $unit->size_unit,
                    'capacity' => $unit->capacity,
                    'status' => $unit->status,
                    'waterSource' => $unit->water_source,
                    'housingSystem' => $unit->housing_system,
                    'suitabilityNotes' => $unit->suitability_notes,
                ])->values(),
            ],
            'cycles' => $cycles,
            'recentLogs' => $recentLogs,
            'recentActivities' => $recentActivities,
            'openTasks' => $openTasks,
            'healthEvents' => $healthEvents,
            'inventoryItems' => $inventoryItems,
            'commodities' => $team->commodities()->orderBy('name')->get()->map(fn (Commodity $commodity): array => [
                'id' => $commodity->id,
                'name' => $commodity->name,
                'farmType' => $commodity->farm_type->value,
                'measurementUnit' => $commodity->measurement_unit,
            ])->values(),
            'financeSummary' => $canViewFinance ? [
                'spent' => Money::toDecimal((int) $spentMinor),
                'netSales' => Money::toDecimal((int) $salesMinor),
                'netMargin' => Money::toDecimal((int) $salesMinor - (int) $spentMinor),
                'harvestCount' => $farm->harvestRecords()->count(),
                'scheduledHealthCount' => $farm->healthEvents()->where('status', HealthEventStatus::Scheduled->value)->count(),
            ] : null,
            'options' => [
                'farmTypes' => FarmType::options(),
                'enterpriseSubtypes' => EnterpriseSubtype::options(),
                'productionUnitTypes' => ProductionUnitType::options(),
                'productionCycleStatuses' => ProductionCycleStatus::options(),
                'commodityRoles' => CommodityRole::options(),
                'cropGrowthStages' => CropGrowthStage::options(),
                'pestPressures' => PestPressure::options(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function logPayload(ProductionLog $log, Team $team): array
    {
        return [
            'id' => $log->id,
            'logDate' => $log->log_date->toDateString(),
            'domain' => $log->domain->value,
            'domainLabel' => $log->domain->label(),
            'productionCycleId' => $log->production_cycle_id,
            'productionCycleName' => $log->productionCycle?->name,
            'productionUnitId' => $log->production_unit_id,
            'productionUnitName' => $log->productionUnit?->name,
            'mortalityCount' => $log->mortality_count,
            'cullCount' => $log->cull_count,
            'gradedOutCount' => $log->graded_out_count,
            'feedUsedKg' => $log->feed_used_kg,
            'feedType' => $log->feed_type,
            'feedPelletSizeMm' => $log->feed_pellet_size_mm,
            'waterUsedLiters' => $log->water_used_liters,
            'averageWeightG' => $log->average_weight_g,
            'sampleSize' => $log->sample_size,
            'eggsCollected' => $log->eggs_collected,
            'eggsDamaged' => $log->eggs_damaged,
            'waterPh' => $log->water_ph,
            'waterTempC' => $log->water_temp_c,
            'dissolvedOxygenMgL' => $log->dissolved_oxygen_mg_l,
            'ammoniaMgL' => $log->ammonia_mg_l,
            'waterExchanged' => $log->water_exchanged,
            'growthStage' => $log->growth_stage?->value,
            'growthStageLabel' => $log->growth_stage?->label(),
            'rainfallMm' => $log->rainfall_mm,
            'irrigationApplied' => $log->irrigation_applied,
            'pestPressure' => $log->pest_pressure?->value,
            'pestPressureLabel' => $log->pest_pressure?->label(),
            'diseaseObserved' => $log->disease_observed,
            'inputAppliedName' => $log->input_applied_name,
            'inputAppliedQuantity' => $log->input_applied_quantity,
            'inputAppliedUnit' => $log->input_applied_unit,
            'inventoryItemName' => $log->inventoryItem?->name,
            'notes' => $log->notes,
            'recordedBy' => $log->recordedBy?->name,
            'evidence' => $log->media->map(fn (Media $media): array => [
                'id' => $media->id,
                'fileName' => $media->file_name,
                'downloadUrl' => route('farm-evidence.show', [$team, $media]),
            ])->values(),
        ];
    }
}
