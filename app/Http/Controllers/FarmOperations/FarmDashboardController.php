<?php

namespace App\Http\Controllers\FarmOperations;

use App\Enums\CommodityRole;
use App\Enums\FarmActivityStatus;
use App\Enums\FarmTaskStatus;
use App\Enums\FarmType;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionPlanChangeType;
use App\Enums\ProductionUnitType;
use App\Enums\WhatsappIntakeStatus;
use App\Http\Controllers\Controller;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\ProductionCycle;
use App\Models\ProductionPlanChange;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

        $latestActivities = $currentTeam->farmActivities()
            ->with(['farm', 'productionUnit', 'productionCycle', 'commodity', 'recordedBy', 'media'])
            ->latest('activity_date')
            ->latest()
            ->limit(8)
            ->get();

        $upcomingTasks = $currentTeam->farmTasks()
            ->with(['farm', 'productionUnit', 'productionCycle', 'assignedTo'])
            ->whereNotIn('status', [FarmTaskStatus::Completed->value, FarmTaskStatus::Cancelled->value])
            ->orderBy('due_on')
            ->latest()
            ->limit(10)
            ->get();

        $pendingIntakes = $currentTeam->whatsappIntakes()
            ->where('review_status', WhatsappIntakeStatus::Pending->value)
            ->with(['farm', 'productionUnit', 'productionCycle', 'commodity', 'importedBy', 'media'])
            ->latest()
            ->limit(8)
            ->get();

        return Inertia::render('farms/Index', [
            'permissions' => $user->toTeamPermissions($currentTeam),
            'stats' => [
                'farms' => $farms->count(),
                'productionUnits' => $farms->sum('production_units_count'),
                'productionCycles' => $farms->sum('production_cycles_count'),
                'commodities' => $commodities->count(),
                'activities' => $currentTeam->farmActivities()->count(),
                'openTasks' => $currentTeam->farmTasks()
                    ->whereNotIn('status', [FarmTaskStatus::Completed->value, FarmTaskStatus::Cancelled->value])
                    ->count(),
                'pendingIntakes' => $currentTeam->whatsappIntakes()
                    ->where('review_status', WhatsappIntakeStatus::Pending->value)
                    ->count(),
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
            'latestActivities' => $latestActivities->map(fn (FarmActivity $activity) => $this->activityPayload($activity, $currentTeam)),
            'upcomingTasks' => $upcomingTasks->map(fn (FarmTask $task) => $this->taskPayload($task)),
            'pendingIntakes' => $pendingIntakes->map(fn (WhatsappIntake $intake) => $this->intakePayload($intake, $currentTeam)),
            'options' => [
                'farmTypes' => FarmType::options(),
                'productionUnitTypes' => ProductionUnitType::options(),
                'productionCycleStatuses' => ProductionCycleStatus::options(),
                'commodityRoles' => CommodityRole::options(),
                'planChangeTypes' => ProductionPlanChangeType::options(),
                'activityStatuses' => FarmActivityStatus::options(),
                'taskStatuses' => FarmTaskStatus::options(),
                'whatsappIntakeStatuses' => WhatsappIntakeStatus::options(),
                'evidenceVisibilities' => [
                    ['value' => 'private', 'label' => 'Private'],
                    ['value' => 'investor_visible', 'label' => 'Investor visible'],
                ],
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

    /**
     * Build a dashboard payload for a diary activity.
     *
     * @return array<string, mixed>
     */
    private function activityPayload(FarmActivity $activity, Team $team): array
    {
        return [
            'id' => $activity->id,
            'farmId' => $activity->farm_id,
            'farmName' => $activity->farm->name,
            'productionUnitId' => $activity->production_unit_id,
            'productionUnitName' => $activity->productionUnit?->name,
            'productionCycleId' => $activity->production_cycle_id,
            'productionCycleName' => $activity->productionCycle?->name,
            'commodityId' => $activity->commodity_id,
            'commodityName' => $activity->commodity?->name,
            'activityDate' => $activity->activity_date->toDateString(),
            'activityType' => $activity->activity_type,
            'description' => $activity->description,
            'inputsUsed' => $activity->inputs_used,
            'labourUsed' => $activity->labour_used,
            'cost' => $activity->cost,
            'remarks' => $activity->remarks,
            'nextActivity' => $activity->next_activity,
            'status' => $activity->status->value,
            'statusLabel' => $activity->status->label(),
            'investorSafeSummary' => $activity->investor_safe_summary,
            'recordedBy' => $activity->recordedBy?->name,
            'evidence' => $activity->media->map(fn (Media $media) => $this->mediaPayload($media, $team)),
        ];
    }

    /**
     * Build a dashboard payload for a task.
     *
     * @return array<string, mixed>
     */
    private function taskPayload(FarmTask $task): array
    {
        return [
            'id' => $task->id,
            'farmId' => $task->farm_id,
            'farmName' => $task->farm->name,
            'productionUnitName' => $task->productionUnit?->name,
            'productionCycleName' => $task->productionCycle?->name,
            'assignedTo' => $task->assignedTo?->name,
            'title' => $task->title,
            'activityType' => $task->activity_type,
            'description' => $task->description,
            'plannedFor' => $task->planned_for?->toDateString(),
            'dueOn' => $task->due_on->toDateString(),
            'reminderAt' => $task->reminder_at?->toISOString(),
            'status' => $task->status->value,
            'statusLabel' => $task->status->label(),
            'statusReason' => $task->status_reason,
            'investorVisible' => $task->investor_visible,
        ];
    }

    /**
     * Build a dashboard payload for pending WhatsApp intake.
     *
     * @return array<string, mixed>
     */
    private function intakePayload(WhatsappIntake $intake, Team $team): array
    {
        return [
            'id' => $intake->id,
            'farmId' => $intake->farm_id,
            'farmName' => $intake->farm?->name,
            'productionUnitId' => $intake->production_unit_id,
            'productionCycleId' => $intake->production_cycle_id,
            'commodityId' => $intake->commodity_id,
            'sourceMessage' => $intake->source_message,
            'sourceSender' => $intake->source_sender,
            'sourceDate' => $intake->source_date?->toDateString(),
            'normalizedActivityDate' => $intake->normalized_activity_date?->toDateString(),
            'normalizedActivityType' => $intake->normalized_activity_type,
            'normalizedDescription' => $intake->normalized_description,
            'normalizedCost' => $intake->normalized_cost,
            'normalizedNextActivity' => $intake->normalized_next_activity,
            'normalizedInvestorSafeSummary' => $intake->normalized_investor_safe_summary,
            'reviewStatus' => $intake->review_status->value,
            'reviewStatusLabel' => $intake->review_status->label(),
            'importedBy' => $intake->importedBy?->name,
            'evidence' => $intake->media->map(fn (Media $media) => $this->mediaPayload($media, $team)),
        ];
    }

    /**
     * Build a private evidence payload.
     *
     * @return array<string, mixed>
     */
    private function mediaPayload(Media $media, Team $team): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'fileName' => $media->file_name,
            'mimeType' => $media->mime_type,
            'size' => $media->size,
            'caption' => $media->getCustomProperty('caption'),
            'visibility' => $media->getCustomProperty('visibility', 'private'),
            'capturedOn' => $media->getCustomProperty('captured_on'),
            'downloadUrl' => route('farm-evidence.show', [$team, $media]),
        ];
    }
}
