<?php

namespace App\Http\Controllers\Investors;

use App\Actions\Investors\RecordInvestorComment;
use App\Enums\InvestorVisibilityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Investors\StoreInvestorCommentRequest;
use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\ExternalTransfer;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\FundingPhase;
use App\Models\InvestorAgreement;
use App\Models\ProductionPlanChange;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvestorCommentController extends Controller
{
    public function store(
        StoreInvestorCommentRequest $request,
        Team $currentTeam,
        InvestorAgreement $investorAgreement,
        RecordInvestorComment $recordInvestorComment,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validated();
        $subject = $this->resolveSubject(
            team: $currentTeam,
            agreement: $investorAgreement,
            user: $user,
            subjectType: is_string($validated['subject_type'] ?? null) ? $validated['subject_type'] : null,
            subjectId: isset($validated['subject_id']) ? (int) $validated['subject_id'] : null,
        );

        $recordInvestorComment->handle(
            team: $currentTeam,
            agreement: $investorAgreement,
            actor: $user,
            body: (string) $validated['body'],
            subject: $subject,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment recorded.')]);

        return back();
    }

    private function resolveSubject(Team $team, InvestorAgreement $agreement, User $user, ?string $subjectType, ?int $subjectId): ?Model
    {
        if ($subjectType === null || $subjectId === null) {
            return null;
        }

        $isInvestor = $agreement->investor_id === $user->id && ! $user->can('manageInvestorAgreements', $team);

        return match ($subjectType) {
            'expense' => $this->approvedAgreementSubject(Expense::query(), $team, $agreement, $subjectId, $isInvestor),
            'funding_phase' => $this->approvedAgreementSubject(FundingPhase::query(), $team, $agreement, $subjectId, $isInvestor),
            'external_transfer' => $this->approvedAgreementSubject(ExternalTransfer::query(), $team, $agreement, $subjectId, $isInvestor),
            'activity' => FarmActivity::query()
                ->where('team_id', $team->id)
                ->where('farm_id', $agreement->farm_id)
                ->when($agreement->production_cycle_id, fn ($query) => $query->where('production_cycle_id', $agreement->production_cycle_id))
                ->whereNotNull('investor_safe_summary')
                ->whereKey($subjectId)
                ->firstOrFail(),
            'task' => FarmTask::query()
                ->where('team_id', $team->id)
                ->where('farm_id', $agreement->farm_id)
                ->when($agreement->production_cycle_id, fn ($query) => $query->where('production_cycle_id', $agreement->production_cycle_id))
                ->where('investor_visible', true)
                ->whereKey($subjectId)
                ->firstOrFail(),
            'plan_change' => $this->approvedAgreementSubject(ProductionPlanChange::query(), $team, $agreement, $subjectId, $isInvestor),
            'approval_request' => ApprovalRequest::query()
                ->where('team_id', $team->id)
                ->where('investor_agreement_id', $agreement->id)
                ->whereKey($subjectId)
                ->firstOrFail(),
            default => null,
        };
    }

    /**
     * Resolve a subject attached to the agreement.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel
     */
    private function approvedAgreementSubject(Builder $query, Team $team, InvestorAgreement $agreement, int $subjectId, bool $isInvestor): Model
    {
        return $query
            ->where('team_id', $team->id)
            ->where('investor_agreement_id', $agreement->id)
            ->when($isInvestor, fn ($query) => $query->where('investor_visibility_status', InvestorVisibilityStatus::Approved->value))
            ->whereKey($subjectId)
            ->firstOrFail();
    }
}
