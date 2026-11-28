<?php

namespace App\Ai\Tools;

use App\Actions\FarmOperations\RecordFarmActivity;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Enums\FarmActivityStatus;
use App\Models\Farm;
use App\Models\ProductionCycle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RecordFarmActivityTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Record a field diary activity (e.g. Vaccination, Feeding, Pond Prep, Land Clearing, Scouting) for a farm.';
    }

    protected function needsApproval(Request $request): Approval
    {
        $type = trim((string) ($request['activity_type'] ?? 'Farm activity'));
        $date = (string) ($request['activity_date'] ?? now()->toDateString());

        return Approval::required("Record '{$type}' diary activity on {$date}");
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('manageFieldWork')) {
            return $this->encodeResult(['error' => 'Not authorized to record farm activities.']);
        }

        $activityDate = $this->parseDateString($request['activity_date'] ?? null);
        $activityType = trim((string) ($request['activity_type'] ?? ''));
        $description = trim((string) ($request['description'] ?? ''));

        if ($activityDate === null || $activityType === '' || $description === '') {
            return $this->encodeResult(['error' => 'YYYY-MM-DD activity_date, activity_type, and description are required.']);
        }

        $farm = Farm::query()->where('team_id', $this->team->id)->find($request->integer('farm_id'));
        if ($farm === null) {
            return $this->encodeResult(['error' => 'Farm not found in this workspace.']);
        }

        $cycleId = $request->integer('production_cycle_id');
        if ($cycleId > 0 && ! ProductionCycle::query()->where('team_id', $this->team->id)->where('farm_id', $farm->id)->whereKey($cycleId)->exists()) {
            return $this->encodeResult(['error' => 'Production cycle not found on the selected farm.']);
        }

        $status = FarmActivityStatus::tryFrom((string) ($request['status'] ?? FarmActivityStatus::Completed->value))
            ?? FarmActivityStatus::Completed;

        $activity = app(RecordFarmActivity::class)->handle($this->team, $this->user, [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycleId > 0 ? $cycleId : null,
            'activity_date' => $activityDate,
            'activity_type' => $activityType,
            'description' => $description,
            'status' => $status->value,
            'investor_safe_summary' => $request->filled('investor_safe_summary')
                ? trim((string) $request['investor_safe_summary'])
                : null,
        ]);

        $this->recordAssistantAudit('farm_activity_recorded', $activity, ['farm_activity_id' => $activity->id]);

        return $this->encodeResult([
            'recorded' => true,
            'farm_activity_id' => $activity->id,
            'farm' => $farm->name,
            'activity_type' => $activity->activity_type,
            'activity_date' => $activityDate,
            'status' => $activity->status->value,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'farm_id' => $schema->integer()->required(),
            'activity_date' => $schema->string()->description('YYYY-MM-DD')->required(),
            'activity_type' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            'production_cycle_id' => $schema->integer(),
            'status' => $schema->string()->enum(['planned', 'in_progress', 'completed', 'delayed']),
            'investor_safe_summary' => $schema->string(),
        ];
    }
}
