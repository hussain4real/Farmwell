<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Finance\EnsureDefaultExpenseCategories;
use App\Actions\Health\ApplyHealthSchedulePreset;
use App\Actions\Inventory\CreateInventoryItem;
use App\Actions\Teams\ResolveInvestorMode;
use App\Enums\CommodityRole;
use App\Enums\FarmTaskStatus;
use App\Enums\ProductionCycleStatus;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmTask;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;
use App\Support\FarmTemplates;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CompleteFarmOnboarding
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private EnsureDefaultExpenseCategories $ensureDefaultExpenseCategories,
        private CreateInventoryItem $createInventoryItem,
        private ApplyHealthSchedulePreset $applyHealthSchedulePreset,
    ) {
        //
    }

    /**
     * Onboard a complete farm operation with units, commodity, initial cycle, and optional starter workflows.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(Team $team, User $actor, array $input): Farm
    {
        return DB::transaction(function () use ($team, $actor, $input): Farm {
            $this->ensureDefaultExpenseCategories->handle($team);

            if (! empty($input['currency'])) {
                TeamSetting::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'setting_group' => 'finance',
                        'setting_key' => 'default_currency',
                    ],
                    [
                        'value' => ['currency' => Money::normalizeCurrency((string) $input['currency'])],
                        'updated_by' => $actor->id,
                    ],
                );
            }

            if (array_key_exists('investor_mode', $input)) {
                TeamSetting::query()->updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'setting_group' => ResolveInvestorMode::SettingGroup,
                        'setting_key' => ResolveInvestorMode::SettingKey,
                    ],
                    [
                        'value' => ['enabled' => (bool) $input['investor_mode']],
                        'updated_by' => $actor->id,
                    ],
                );
            }

            $template = ! empty($input['template_key'])
                ? FarmTemplates::find((string) $input['template_key'])
                : null;

            $farm = Farm::create([
                'team_id' => $team->id,
                'name' => $input['farm_name'],
                'farm_type' => $input['farm_type'],
                'location' => $input['location'] ?? null,
                'contact_name' => $input['contact_name'] ?? null,
                'contact_phone' => $input['contact_phone'] ?? null,
                'description' => $input['description'] ?? null,
                'status' => 'active',
            ]);

            $unitIds = [];
            /** @var array<int, array<string, mixed>> $unitsInput */
            $unitsInput = $input['units'];

            foreach ($unitsInput as $unitData) {
                $unit = ProductionUnit::create([
                    'team_id' => $team->id,
                    'farm_id' => $farm->id,
                    'name' => $unitData['name'],
                    'unit_type' => $unitData['unit_type'],
                    'size' => $unitData['size'] ?? null,
                    'size_unit' => $unitData['size_unit'] ?? null,
                    'capacity' => $unitData['capacity'] ?? null,
                    'water_source' => $unitData['water_source'] ?? null,
                    'housing_system' => $unitData['housing_system'] ?? null,
                    'status' => 'in_use',
                ]);

                $unitIds[] = $unit->id;
            }

            $commodity = Commodity::query()->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'name' => (string) $input['commodity_name'],
                ],
                [
                    'farm_type' => $input['farm_type'],
                    'measurement_unit' => $input['commodity_unit'] ?? null,
                ],
            );

            $plannedStart = CarbonImmutable::parse((string) $input['planned_start_on']);
            $cycleStatus = $input['cycle_status'] ?? ProductionCycleStatus::Active->value;
            $initialStock = isset($input['initial_stock_count']) && $input['initial_stock_count'] !== null
                ? (int) $input['initial_stock_count']
                : null;

            $cycle = ProductionCycle::create([
                'team_id' => $team->id,
                'farm_id' => $farm->id,
                'name' => $input['cycle_name'],
                'season' => $input['season'] ?? (string) $plannedStart->year,
                'farm_type' => $input['farm_type'],
                'enterprise_subtype' => $input['enterprise_subtype'] ?? null,
                'breed_or_variety' => $input['breed_or_variety'] ?? null,
                'planned_start_on' => $plannedStart->toDateString(),
                'planned_end_on' => $input['planned_end_on'] ?? null,
                'actual_start_on' => $cycleStatus === ProductionCycleStatus::Active->value ? $plannedStart->toDateString() : null,
                'expected_output_quantity' => $input['expected_output_quantity'] ?? null,
                'expected_output_unit' => $input['expected_output_unit'] ?? ($input['commodity_unit'] ?? null),
                'initial_stock_count' => $initialStock,
                'current_stock_count' => $initialStock,
                'initial_average_weight_g' => $input['initial_average_weight_g'] ?? null,
                'target_weight_g' => $input['target_weight_g'] ?? null,
                'current_growth_stage' => $input['current_growth_stage'] ?? null,
                'status' => $cycleStatus,
                'plan_summary' => $input['plan_summary'] ?? null,
            ]);

            $cycle->productionUnits()->sync($unitIds);
            $cycle->commodities()->sync([
                $commodity->id => [
                    'role' => CommodityRole::Primary->value,
                    'expected_output_quantity' => $input['expected_output_quantity'] ?? null,
                    'expected_output_unit' => $input['expected_output_unit'] ?? ($input['commodity_unit'] ?? null),
                ],
            ]);

            if (! empty($input['create_starter_tasks']) && $template !== null) {
                /** @var list<array{title: string, activity_type: string, day_offset: int}> $starterTasks */
                $starterTasks = $template['starterTasks'];

                foreach ($starterTasks as $taskTemplate) {
                    FarmTask::create([
                        'team_id' => $team->id,
                        'farm_id' => $farm->id,
                        'production_unit_id' => $unitIds[0] ?? null,
                        'production_cycle_id' => $cycle->id,
                        'created_by_id' => $actor->id,
                        'title' => $taskTemplate['title'],
                        'activity_type' => $taskTemplate['activity_type'],
                        'planned_for' => $plannedStart->addDays($taskTemplate['day_offset'])->toDateString(),
                        'due_on' => $plannedStart->addDays($taskTemplate['day_offset'])->toDateString(),
                        'status' => FarmTaskStatus::Planned->value,
                        'investor_visible' => false,
                    ]);
                }
            }

            if (! empty($input['create_starter_inventory']) && $template !== null) {
                /** @var list<array{name: string, category: string, unit: string, unit_weight_kg: float|int|null, reorder_level: float|int}> $starterItems */
                $starterItems = $template['starterInventory'];

                foreach ($starterItems as $itemTemplate) {
                    $exists = $team->inventoryItems()
                        ->where('farm_id', $farm->id)
                        ->where('name', $itemTemplate['name'])
                        ->exists();

                    if (! $exists) {
                        $this->createInventoryItem->handle($team, $actor, [
                            'farm_id' => $farm->id,
                            'name' => $itemTemplate['name'],
                            'category' => $itemTemplate['category'],
                            'unit' => $itemTemplate['unit'],
                            'unit_weight_kg' => $itemTemplate['unit_weight_kg'],
                            'current_quantity' => 0,
                            'reorder_level' => $itemTemplate['reorder_level'],
                            'unit_cost_minor' => 0,
                        ]);
                    }
                }
            }

            if (! empty($input['create_health_schedule']) && $template !== null && ! empty($template['healthPreset'])) {
                $this->applyHealthSchedulePreset->handle($team, $cycle, $actor, (string) $template['healthPreset']);
            }

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'farm.onboarded',
                subject: $farm,
                newValues: [
                    'template_key' => $input['template_key'] ?? null,
                    'farm_type' => $farm->farm_type->value,
                    'unit_count' => count($unitIds),
                    'cycle_id' => $cycle->id,
                ],
            );

            return $farm;
        });
    }
}
