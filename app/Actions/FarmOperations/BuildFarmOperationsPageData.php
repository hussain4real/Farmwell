<?php

namespace App\Actions\FarmOperations;

use App\Actions\Finance\BuildFinancePageData;
use App\Actions\Harvests\BuildHarvestPageData;
use App\Enums\CommodityRole;
use App\Enums\FarmActivityStatus;
use App\Enums\FarmTaskStatus;
use App\Enums\FarmType;
use App\Enums\InvestorAgreementStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionPlanChangeType;
use App\Enums\ProductionUnitType;
use App\Enums\WhatsappIntakeStatus;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\ProductionPlanChange;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BuildFarmOperationsPageData
{
    public function __construct(
        private BuildFinancePageData $financePageData,
        private BuildHarvestPageData $harvestPageData,
    ) {
        //
    }

    /**
     * Build the high-level farm operating dashboard payload.
     *
     * @return array<string, mixed>
     */
    public function dashboard(Team $team, User $user): array
    {
        return [
            'permissions' => $user->toTeamPermissions($team),
            'stats' => $this->stats($team),
            'latestActivities' => $this->latestActivities($team, 4)->map(fn (FarmActivity $activity) => $this->activityPayload($activity, $team)),
            'upcomingTasks' => $this->upcomingTasks($team, 5)->map(fn (FarmTask $task) => $this->taskPayload($task)),
            'pendingIntakes' => $this->pendingIntakes($team, 4)->map(fn (WhatsappIntake $intake) => $this->intakePayload($intake, $team)),
            'latestPlanChanges' => $this->latestPlanChanges($team, 4)->map(fn (ProductionPlanChange $planChange) => $this->planChangePayload($planChange)),
            'financeSummary' => $user->can('viewFinance', $team) ? $this->financePageData->dashboardSummary($team) : null,
            'harvestSummary' => $user->can('viewFinance', $team) ? $this->harvestPageData->dashboardSummary($team) : null,
        ];
    }

    /**
     * Build the farm operations page payload.
     *
     * @return array<string, mixed>
     */
    public function farmOperations(Team $team, User $user): array
    {
        $farms = $this->farms($team);
        $commodities = $this->commodities($team);

        return [
            'permissions' => $user->toTeamPermissions($team),
            'stats' => [
                'farms' => $farms->count(),
                'productionUnits' => $farms->sum('production_units_count'),
                'productionCycles' => $farms->sum('production_cycles_count'),
                'commodities' => $commodities->count(),
                'activities' => $team->farmActivities()->count(),
                'openTasks' => $this->openTaskCount($team),
                'pendingIntakes' => $this->pendingIntakeCount($team),
            ],
            'farms' => $farms->map(fn (Farm $farm) => $this->farmPayload($farm)),
            'commodities' => $commodities->map(fn (Commodity $commodity) => $this->commodityPayload($commodity)),
            'investorAgreements' => $this->investorAgreements($team)->map(fn (InvestorAgreement $agreement) => $this->investorAgreementPayload($agreement)),
            'latestPlanChanges' => $this->latestPlanChanges($team)->map(fn (ProductionPlanChange $planChange) => $this->planChangePayload($planChange)),
            'options' => [
                'farmTypes' => FarmType::options(),
                'productionUnitTypes' => ProductionUnitType::options(),
                'productionCycleStatuses' => ProductionCycleStatus::options(),
                'commodityRoles' => CommodityRole::options(),
                'planChangeTypes' => ProductionPlanChangeType::options(),
                'investorVisibilityStatuses' => InvestorVisibilityStatus::options(),
            ],
        ];
    }

    /**
     * Build the field diary page payload.
     *
     * @return array<string, mixed>
     */
    public function fieldDiary(Team $team, User $user): array
    {
        return [
            'permissions' => $user->toTeamPermissions($team),
            'farms' => $this->farms($team)->map(fn (Farm $farm) => $this->farmPayload($farm)),
            'commodities' => $this->commodities($team)->map(fn (Commodity $commodity) => $this->commodityPayload($commodity)),
            'latestActivities' => $this->latestActivities($team, 12)->map(fn (FarmActivity $activity) => $this->activityPayload($activity, $team)),
            'options' => [
                'activityStatuses' => FarmActivityStatus::options(),
                'evidenceVisibilities' => $this->evidenceVisibilityOptions(),
            ],
        ];
    }

    /**
     * Build the task calendar page payload.
     *
     * @return array<string, mixed>
     */
    public function taskCalendar(Team $team, User $user): array
    {
        return [
            'permissions' => $user->toTeamPermissions($team),
            'farms' => $this->farms($team)->map(fn (Farm $farm) => $this->farmPayload($farm)),
            'upcomingTasks' => $this->upcomingTasks($team, 25)->map(fn (FarmTask $task) => $this->taskPayload($task)),
            'options' => [
                'taskStatuses' => FarmTaskStatus::options(),
            ],
        ];
    }

    /**
     * Build the WhatsApp intake review page payload.
     *
     * @return array<string, mixed>
     */
    public function whatsappIntake(Team $team, User $user): array
    {
        return [
            'permissions' => $user->toTeamPermissions($team),
            'farms' => $this->farms($team)->map(fn (Farm $farm) => $this->farmPayload($farm)),
            'commodities' => $this->commodities($team)->map(fn (Commodity $commodity) => $this->commodityPayload($commodity)),
            'pendingIntakes' => $this->pendingIntakes($team, 20)->map(fn (WhatsappIntake $intake) => $this->intakePayload($intake, $team)),
            'options' => [
                'whatsappIntakeStatuses' => WhatsappIntakeStatus::options(),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function stats(Team $team): array
    {
        return [
            'farms' => $team->farms()->count(),
            'productionUnits' => $team->productionUnits()->count(),
            'productionCycles' => $team->productionCycles()->count(),
            'commodities' => $team->commodities()->count(),
            'activities' => $team->farmActivities()->count(),
            'openTasks' => $this->openTaskCount($team),
            'pendingIntakes' => $this->pendingIntakeCount($team),
        ];
    }

    private function openTaskCount(Team $team): int
    {
        return $team->farmTasks()
            ->whereNotIn('status', [FarmTaskStatus::Completed->value, FarmTaskStatus::Cancelled->value])
            ->count();
    }

    private function pendingIntakeCount(Team $team): int
    {
        return $team->whatsappIntakes()
            ->where('review_status', WhatsappIntakeStatus::Pending->value)
            ->count();
    }

    /**
     * @return Collection<int, Farm>
     */
    private function farms(Team $team): Collection
    {
        return $team->farms()
            ->with([
                'productionUnits' => fn ($query) => $query->orderBy('name'),
                'productionCycles' => fn ($query) => $query
                    ->with(['productionUnits', 'commodities'])
                    ->latest(),
            ])
            ->withCount(['productionUnits', 'productionCycles'])
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
     * @return Collection<int, ProductionPlanChange>
     */
    private function latestPlanChanges(Team $team, int $limit = 5): Collection
    {
        return ProductionPlanChange::query()
            ->where('team_id', $team->id)
            ->with(['productionCycle.farm', 'actor', 'investorAgreement'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, FarmActivity>
     */
    private function latestActivities(Team $team, int $limit): Collection
    {
        return $team->farmActivities()
            ->with(['farm', 'productionUnit', 'productionCycle', 'commodity', 'recordedBy', 'media'])
            ->latest('activity_date')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, FarmTask>
     */
    private function upcomingTasks(Team $team, int $limit): Collection
    {
        return $team->farmTasks()
            ->with(['farm', 'productionUnit', 'productionCycle', 'assignedTo'])
            ->whereNotIn('status', [FarmTaskStatus::Completed->value, FarmTaskStatus::Cancelled->value])
            ->orderBy('due_on')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, WhatsappIntake>
     */
    private function pendingIntakes(Team $team, int $limit): Collection
    {
        return $team->whatsappIntakes()
            ->where('review_status', WhatsappIntakeStatus::Pending->value)
            ->with(['farm', 'productionUnit', 'productionCycle', 'commodity', 'importedBy', 'media'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function commodityPayload(Commodity $commodity): array
    {
        return [
            'id' => $commodity->id,
            'name' => $commodity->name,
            'farmType' => $commodity->farm_type->value,
            'farmTypeLabel' => $commodity->farm_type->label(),
            'measurementUnit' => $commodity->measurement_unit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planChangePayload(ProductionPlanChange $planChange): array
    {
        return [
            'id' => $planChange->id,
            'cycleId' => $planChange->production_cycle_id,
            'cycleName' => $planChange->productionCycle->name,
            'farmName' => $planChange->productionCycle->farm->name,
            'changeType' => $planChange->change_type->value,
            'changeTypeLabel' => $planChange->change_type->label(),
            'reason' => $planChange->reason,
            'impact' => $planChange->impact,
            'investorAgreementId' => $planChange->investor_agreement_id,
            'investorAgreementTitle' => $planChange->investorAgreement?->title,
            'investorVisibilityStatus' => $planChange->investor_visibility_status->value,
            'investorVisibilityStatusLabel' => $planChange->investor_visibility_status->label(),
            'recordedBy' => $planChange->actor?->name,
            'createdAt' => $planChange->created_at?->toISOString(),
        ];
    }

    /**
     * @return Collection<int, InvestorAgreement>
     */
    private function investorAgreements(Team $team): Collection
    {
        return $team->investorAgreements()
            ->with('investor')
            ->whereIn('status', [InvestorAgreementStatus::Draft->value, InvestorAgreementStatus::Active->value])
            ->latest()
            ->get();
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
        ];
    }

    /**
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

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function evidenceVisibilityOptions(): array
    {
        return [
            ['value' => 'private', 'label' => 'Private'],
            ['value' => 'investor_visible', 'label' => 'Investor visible'],
        ];
    }
}
