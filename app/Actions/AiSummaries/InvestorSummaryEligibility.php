<?php

namespace App\Actions\AiSummaries;

use App\Enums\InvestorAgreementStatus;
use App\Models\AiSummary;
use App\Models\FarmActivity;
use App\Models\InvestorAgreement;
use Illuminate\Database\Eloquent\Builder;

class InvestorSummaryEligibility
{
    /** @return Builder<AiSummary> */
    public function publishedFor(InvestorAgreement $agreement): Builder
    {
        return AiSummary::query()
            ->where('team_id', $agreement->team_id)
            ->where('investor_agreement_id', $agreement->id)
            ->where('type', 'investor_progress')
            ->where('status', 'published')
            ->whereNotNull('final_text')
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->latest('id');
    }

    public function forStatement(InvestorAgreement $agreement, string $start, string $end, ?int $summaryId = null): ?AiSummary
    {
        return $this->publishedFor($agreement)
            ->when($summaryId !== null, fn (Builder $query) => $query->whereKey($summaryId))
            ->cursor()
            ->first(fn (AiSummary $summary): bool => $this->isCurrent($summary, $agreement)
                && collect($summary->source_snapshot)->every(fn (array $source): bool => $source['date'] >= $start && $source['date'] <= $end));
    }

    public function isCurrent(AiSummary $summary, InvestorAgreement $agreement, bool $lock = false): bool
    {
        if ($summary->type !== 'investor_progress'
            || $summary->team_id !== $agreement->team_id
            || $summary->investor_agreement_id !== $agreement->id
            || ! in_array($agreement->status, [InvestorAgreementStatus::Active, InvestorAgreementStatus::Completed], true)
            || $summary->generation_context != [
                'team_id' => $agreement->team_id,
                'investor_id' => $agreement->investor_id,
                'farm_id' => $agreement->farm_id,
                'production_cycle_id' => $agreement->production_cycle_id,
            ]) {
            return false;
        }

        $snapshot = $summary->source_snapshot;
        if (! is_array($snapshot) || $snapshot === []) {
            return false;
        }

        $ids = [];
        foreach ($snapshot as $record) {
            if (! is_array($record) || ! isset($record['id']) || ! is_int($record['id'])) {
                return false;
            }
            $ids[] = $record['id'];
        }
        if (count(array_unique($ids)) !== count($ids)) {
            return false;
        }

        $activities = FarmActivity::query()
            ->where('team_id', $agreement->team_id)
            ->where('farm_id', $agreement->farm_id)
            ->when($agreement->production_cycle_id, fn ($query) => $query->where('production_cycle_id', $agreement->production_cycle_id))
            ->whereNotNull('investor_safe_summary')
            ->whereIn('id', $ids)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'activity_date', 'activity_type', 'status', 'investor_safe_summary'])
            ->keyBy('id');

        foreach ($snapshot as $record) {
            $activity = $activities->get($record['id']);
            if (! $activity instanceof FarmActivity || $record != [
                'id' => $activity->id,
                'date' => $activity->activity_date->toDateString(),
                'type' => $activity->activity_type,
                'status' => $activity->status->value,
                'summary' => $activity->investor_safe_summary,
            ]) {
                return false;
            }
        }

        return true;
    }
}
