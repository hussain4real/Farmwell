<?php

namespace App\Jobs;

use App\Ai\Agents\InternalFarmSummary;
use App\Ai\Agents\InvestorProgressSummary;
use App\Enums\FarmTaskStatus;
use App\Enums\InvestorAgreementStatus;
use App\Enums\WhatsappIntakeStatus;
use App\Models\AiSummary;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\WhatsappIntake;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use RuntimeException;
use Throwable;

class GenerateAiSummary implements ShouldQueue
{
    use Queueable;

    public const ProviderTimeout = 45;

    public int $tries = 3;

    public int $timeout = 110;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(public int $summaryId) {}

    public function handle(): void
    {
        try {
            $this->generate();
        } catch (Throwable $exception) {
            if ($this->job !== null && ! $this->isRetryable($exception)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    private function generate(): void
    {
        $summary = AiSummary::query()->whereKey($this->summaryId)->where('status', 'queued')->first();

        if ($summary === null) {
            return;
        }

        $type = $summary->type;
        if (! in_array($type, ['investor_progress', 'weekly', 'risk_decision', 'intake_cleanup'], true)) {
            throw new RuntimeException('The summary type is not supported.');
        }

        $agreement = null;
        if ($type === 'investor_progress') {
            $agreement = InvestorAgreement::query()
                ->whereKey($summary->investor_agreement_id)
                ->where('team_id', $summary->team_id)
                ->whereIn('status', [InvestorAgreementStatus::Active->value, InvestorAgreementStatus::Completed->value])
                ->first();
            if ($agreement === null) {
                throw new RuntimeException('The agreement is no longer eligible for a summary.');
            }
            $farmId = $agreement->farm_id;
            $cycleId = $agreement->production_cycle_id;
        } else {
            $farmId = $summary->farm_id;
            $cycleId = $summary->production_cycle_id;
            if (! $farmId || ! Farm::query()->whereKey($farmId)->where('team_id', $summary->team_id)->exists()) {
                throw new RuntimeException('The farm is not eligible for a summary.');
            }
            if ($cycleId && ! ProductionCycle::query()->whereKey($cycleId)->where('team_id', $summary->team_id)->where('farm_id', $farmId)->exists()) {
                throw new RuntimeException('The production cycle is not eligible for a summary.');
            }
        }

        $activities = new Collection;
        $tasks = new Collection;
        $intake = null;

        if ($type !== 'intake_cleanup') {
            $activities = FarmActivity::query()
                ->where('team_id', $summary->team_id)
                ->where('farm_id', $farmId)
                ->when($cycleId, fn ($query) => $query->where('production_cycle_id', $cycleId))
                ->whereNotNull('investor_safe_summary')
                ->when($type === 'weekly', fn ($query) => $query->whereDate('activity_date', '>=', now()->subDays(6)->toDateString())->whereDate('activity_date', '<=', now()->toDateString()))
                ->latest('activity_date')->latest('id')->limit(20)
                ->get(['id', 'activity_date', 'activity_type', 'status', 'investor_safe_summary']);
        }

        if ($type === 'weekly' || $type === 'risk_decision') {
            $tasks = FarmTask::query()
                ->where('team_id', $summary->team_id)
                ->where('farm_id', $farmId)
                ->when($cycleId, fn ($query) => $query->where('production_cycle_id', $cycleId))
                ->where('investor_visible', true)
                ->when($type === 'weekly', fn ($query) => $query->whereDate('due_on', '>=', now()->subDays(6)->toDateString())->whereDate('due_on', '<=', now()->toDateString()))
                ->when($type === 'risk_decision', fn ($query) => $query->whereIn('status', [FarmTaskStatus::Blocked->value, FarmTaskStatus::Delayed->value]))
                ->latest('due_on')->latest('id')->limit(20)
                ->get(['id', 'title', 'activity_type', 'status', 'due_on']);
        }

        if ($type === 'intake_cleanup') {
            $intake = WhatsappIntake::query()
                ->whereKey($summary->whatsapp_intake_id)
                ->where('team_id', $summary->team_id)
                ->where('farm_id', $farmId)
                ->when($cycleId, fn ($query) => $query->where('production_cycle_id', $cycleId))
                ->where('review_status', WhatsappIntakeStatus::Pending->value)
                ->first(['id', 'source_date']);
            if ($intake !== null && (! is_string($summary->input_text) || trim($summary->input_text) === '')) {
                throw new RuntimeException('A sanitized intake excerpt is required.');
            }
        }

        if ($activities->isEmpty() && $tasks->isEmpty() && $intake === null) {
            throw new RuntimeException('No eligible records are available for a summary.');
        }

        $activityRecords = $activities->map(fn (FarmActivity $activity): array => [
            'id' => $activity->id,
            'date' => $activity->activity_date->toDateString(),
            'type' => $activity->activity_type,
            'status' => $activity->status->value,
            'summary' => $activity->investor_safe_summary,
        ])->all();
        $taskRecords = $tasks->map(fn (FarmTask $task): array => [
            'id' => $task->id,
            'title' => $task->title,
            'type' => $task->activity_type,
            'status' => $task->status->value,
            'due_on' => $task->due_on?->toDateString(),
        ])->all();
        $intakeRecord = $intake === null ? null : [
            'id' => $intake->id,
            'date' => $intake->source_date?->toDateString(),
            'message' => $summary->input_text,
        ];

        $instruction = match ($type) {
            'investor_progress' => 'Summarize these investor-safe farm activities for review:',
            'weekly' => 'Draft a weekly farm progress summary from these records for internal review:',
            'risk_decision' => 'Identify risks, uncertainties, and decisions needing human attention from these records for internal review:',
            'intake_cleanup' => 'Suggest a clear activity record from this intake message for internal review. Do not execute or convert it:',
        };
        $records = $type === 'investor_progress' ? $activityRecords : [
            'activities' => $activityRecords, 'tasks' => $taskRecords, 'intake' => $intakeRecord,
        ];
        $prompt = $instruction."\n".json_encode($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $summary->fill([
            'prompt' => $prompt,
            'source_activity_ids' => $activities->modelKeys(),
            'source_records' => [
                'activities' => $activities->modelKeys(),
                'tasks' => $tasks->modelKeys(),
                'whatsapp_intakes' => $intake === null ? [] : [$intake->id],
            ],
            'source_snapshot' => $activityRecords,
            'generation_context' => [
                'team_id' => $summary->team_id,
                'investor_id' => $agreement?->investor_id,
                'farm_id' => $farmId,
                'production_cycle_id' => $cycleId,
            ],
        ]);
        if ($this->job !== null) {
            $connection = $this->job->getConnectionName();
            $retryAfter = config('queue.connections.'.$connection.'.retry_after');
            if (is_numeric($retryAfter) && (int) $retryAfter <= $this->timeout) {
                throw new RuntimeException('Queue retry_after must exceed the AI summary job timeout.');
            }
        }
        $model = config('ai.summary_model');
        $agent = $type === 'investor_progress' ? new InvestorProgressSummary : new InternalFarmSummary;
        $response = $agent->prompt($prompt, model: is_string($model) && $model !== '' ? $model : null, timeout: self::ProviderTimeout);

        AiSummary::query()->whereKey($summary->id)->where('status', 'queued')->update([...Arr::only($summary->getAttributes(), [
            'prompt', 'source_activity_ids', 'source_records', 'source_snapshot', 'generation_context',
        ]),
            'generated_text' => $response->text,
            'status' => 'draft',
        ]);
    }

    private function isRetryable(Throwable $exception): bool
    {
        if ($exception instanceof RateLimitedException) {
            $previous = $exception->getPrevious();
            if ($previous instanceof RequestException) {
                $code = $previous->response->json('error.code');
                $type = $previous->response->json('error.type');
                if (in_array($code, ['insufficient_quota', 'billing_hard_limit_reached'], true)
                    || in_array($type, ['insufficient_quota', 'billing_hard_limit_reached'], true)) {
                    return false;
                }
            }

            return true;
        }

        if ($exception instanceof ProviderConnectionException || $exception instanceof ProviderOverloadedException) {
            return true;
        }

        return $exception instanceof RequestException
            && in_array($exception->response->status(), [408, 500, 502, 503, 504, 520, 522, 524], true);
    }

    public function failed(?Throwable $exception): void
    {
        AiSummary::query()->whereKey($this->summaryId)->where('status', 'queued')->update(['status' => 'failed']);
    }
}
