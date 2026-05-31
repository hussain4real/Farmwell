<?php

namespace App\Http\Controllers\FarmOperations;

use App\Enums\CommodityRole;
use App\Enums\FarmType;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionPlanChangeType;
use App\Enums\ProductionUnitType;
use App\Http\Controllers\Controller;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\ProductionPlanChange;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FarmDashboardController extends Controller
{
    /**
     * Show the tenant-scoped farm operating dashboard.
     */
    public function __invoke(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewFarmOperations', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        $farms = $currentTeam->farms()
            ->with([
                'productionUnits' => fn ($query) => $query->orderBy('name'),
                'productionCycles' => fn ($query) => $query
                    ->with(['productionUnits', 'commodities'])
                    ->latest(),
            ])
            ->withCount(['productionUnits', 'productionCycles'])
            ->orderBy('name')
            ->get();

        $commodities = $currentTeam->commodities()
            ->orderBy('name')
            ->get();

        $latestPlanChanges = ProductionPlanChange::query()
            ->where('team_id', $currentTeam->id)
            ->with(['productionCycle.farm', 'actor'])
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('farms/Index', [
            'permissions' => $user->toTeamPermissions($currentTeam),
            'stats' => [
                'farms' => $farms->count(),
                'productionUnits' => $farms->sum('production_units_count'),
                'productionCycles' => $farms->sum('production_cycles_count'),
                'commodities' => $commodities->count(),
            ],
            'farms' => $farms->map(fn (Farm $farm) => $this->farmPayload($farm)),
            'commodities' => $commodities->map(fn (Commodity $commodity) => [
                'id' => $commodity->id,
                'name' => $commodity->name,
                'farmType' => $commodity->farm_type->value,
                'farmTypeLabel' => $commodity->farm_type->label(),
                'measurementUnit' => $commodity->measurement_unit,
            ]),
            'latestPlanChanges' => $latestPlanChanges->map(fn (ProductionPlanChange $planChange) => [
                'id' => $planChange->id,
                'cycleId' => $planChange->production_cycle_id,
                'cycleName' => $planChange->productionCycle->name,
                'farmName' => $planChange->productionCycle->farm->name,
                'changeType' => $planChange->change_type->value,
                'changeTypeLabel' => $planChange->change_type->label(),
                'reason' => $planChange->reason,
                'impact' => $planChange->impact,
                'recordedBy' => $planChange->actor?->name,
                'createdAt' => $planChange->created_at?->toISOString(),
            ]),
            'options' => [
                'farmTypes' => FarmType::options(),
                'productionUnitTypes' => ProductionUnitType::options(),
                'productionCycleStatuses' => ProductionCycleStatus::options(),
                'commodityRoles' => CommodityRole::options(),
                'planChangeTypes' => ProductionPlanChangeType::options(),
            ],
        ]);
    }

    /**
     * Build a dashboard payload for a farm.
     *
     * @return array<string, mixed>
     */
    private function farmPayload(Farm $farm): array
    {
        return [
            'id' => $farm->id,
            'name' => $farm->name,
            'farmType' => $farm->farm_type->value,
            'farmTypeLabel' => $farm->farm_type->label(),
            'location' => $farm->location,
            'status' => $farm->status,
            'productionUnitsCount' => $farm->production_units_count,
            'productionCyclesCount' => $farm->production_cycles_count,
            'productionUnits' => $farm->productionUnits->map(fn (ProductionUnit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'unitType' => $unit->unit_type->value,
                'unitTypeLabel' => $unit->unit_type->label(),
                'size' => $unit->size,
                'sizeUnit' => $unit->size_unit,
                'status' => $unit->status,
            ]),
            'productionCycles' => $farm->productionCycles->map(fn (ProductionCycle $cycle) => [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'season' => $cycle->season,
                'farmType' => $cycle->farm_type->value,
                'farmTypeLabel' => $cycle->farm_type->label(),
                'productionMethod' => $cycle->production_method,
                'plannedStartOn' => $cycle->planned_start_on->toDateString(),
                'plannedEndOn' => $cycle->planned_end_on?->toDateString(),
                'expectedOutputQuantity' => $cycle->expected_output_quantity,
                'expectedOutputUnit' => $cycle->expected_output_unit,
                'status' => $cycle->status->value,
                'statusLabel' => $cycle->status->label(),
                'planVersion' => $cycle->plan_version,
                'planSummary' => $cycle->plan_summary,
                'productionUnits' => $cycle->productionUnits->map(fn (ProductionUnit $unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                ]),
                'commodities' => $cycle->commodities->map(fn (Commodity $commodity) => $this->cycleCommodityPayload($commodity)),
            ]),
        ];
    }

    /**
     * Build a dashboard payload for a cycle commodity pivot.
     *
     * @return array<string, mixed>
     */
    private function cycleCommodityPayload(Commodity $commodity): array
    {
        $pivot = $commodity->getRelationValue('pivot');
        assert($pivot instanceof Pivot);

        $role = (string) $pivot->getAttribute('role');

        return [
            'id' => $commodity->id,
            'name' => $commodity->name,
            'role' => $role,
            'roleLabel' => CommodityRole::tryFrom($role)?->label() ?? ucfirst($role),
            'expectedOutputQuantity' => $pivot->getAttribute('expected_output_quantity'),
            'expectedOutputUnit' => $pivot->getAttribute('expected_output_unit'),
        ];
    }
}
