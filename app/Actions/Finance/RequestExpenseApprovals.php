<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Investors\CreateApprovalRequest;
use App\Actions\Investors\DetermineInvestorApprovalRequirement;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\ExpenseStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RequestExpenseApprovals
{
    public function __construct(
        private CreateApprovalRequest $createApprovalRequest,
        private DetermineInvestorApprovalRequirement $determineInvestorApprovalRequirement,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(Team $team, User $actor, Expense $expense, bool $correcting = false): void
    {
        $agreement = $expense->investorAgreement;
        $refreshRelatedOverruns = $correcting && $expense->budget_line_id !== null && $expense->wasChanged('amount_minor');
        $overrunMinor = $agreement !== null || $refreshRelatedOverruns ? $this->budgetOverrunMinor($team, $expense) : 0;

        if ($refreshRelatedOverruns) {
            $this->refreshRelatedOverrunRequests($team, $actor, $expense, $overrunMinor);
        }

        if ($agreement === null) {
            return;
        }

        $approvals = $correcting
            ? $expense->approvalRequests()
                ->where('team_id', $team->id)
                ->whereIn('request_type', [ApprovalRequestType::Expense, ApprovalRequestType::BudgetOverrun])
                ->where('status', '!=', ApprovalRequestStatus::Cancelled)
                ->latest('id')
                ->lockForUpdate()->get()
            : new Collection;
        $returnForReview = $correcting && ($approvals->isNotEmpty()
            || $expense->investor_visibility_status !== InvestorVisibilityStatus::Private);

        foreach ([ApprovalRequestType::Expense, ApprovalRequestType::BudgetOverrun] as $type) {
            $amountMinor = $type === ApprovalRequestType::Expense
                ? $expense->amount_minor
                : $overrunMinor;
            $applies = $type === ApprovalRequestType::Expense || $amountMinor > 0;
            $existing = $approvals->where('request_type', $type);
            $open = $applies ? $this->openApproval($existing, $agreement->id) : null;

            foreach ($existing->except($open?->id) as $approval) {
                $this->supersede($team, $actor, $approval);
            }

            if (! $applies) {
                continue;
            }

            if ($open !== null) {
                $open->update(['requested_amount_minor' => $amountMinor]);

                continue;
            }

            $requirement = $this->determineInvestorApprovalRequirement->handle(
                team: $team,
                agreement: $agreement,
                requestType: $type,
                amountMinor: $amountMinor,
                category: $expense->expenseCategory,
                fundingPhase: $expense->fundingPhase,
                farmType: $expense->farm->farm_type->value,
            );

            if ($requirement['required'] || $returnForReview) {
                $this->createApprovalRequest->handle(
                    team: $team,
                    agreement: $agreement,
                    actor: $actor,
                    subject: $expense,
                    requestType: $type,
                    triggerType: $requirement['triggerType'],
                    requestedAmountMinor: $amountMinor,
                    currency: $expense->currency,
                    rule: $requirement['rule'],
                    category: $expense->expenseCategory,
                    thresholdAmountMinor: $requirement['thresholdAmountMinor'],
                    comment: $requirement['required'] ? $requirement['reason'] : 'A corrected expense requires investor review.',
                );
            }
        }
    }

    /** @param Collection<int, ApprovalRequest> $approvals */
    private function openApproval(Collection $approvals, int $agreementId): ?ApprovalRequest
    {
        $matching = $approvals->where('investor_agreement_id', $agreementId);

        return $matching->firstWhere('status', ApprovalRequestStatus::ClarificationRequested)
            ?? $matching->firstWhere('status', ApprovalRequestStatus::Pending);
    }

    private function refreshRelatedOverrunRequests(Team $team, User $actor, Expense $expense, int $overrunMinor): void
    {
        $openStatuses = [ApprovalRequestStatus::Pending, ApprovalRequestStatus::ClarificationRequested];
        $relatedExpenses = $team->expenses()
            ->where('budget_line_id', $expense->budget_line_id)
            ->whereKeyNot($expense->id)
            ->whereHas('approvalRequests', fn ($query) => $query
                ->where('approval_requests.team_id', $team->id)
                ->whereColumn('approval_requests.investor_agreement_id', 'expenses.investor_agreement_id')
                ->where('request_type', ApprovalRequestType::BudgetOverrun)
                ->whereIn('status', $openStatuses))
            ->with(['approvalRequests' => fn ($query) => $query
                ->where('team_id', $team->id)
                ->whereIn('request_type', [ApprovalRequestType::Expense, ApprovalRequestType::BudgetOverrun])
                ->where('status', '!=', ApprovalRequestStatus::Cancelled)
                ->latest('id')
                ->lockForUpdate()])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($relatedExpenses as $related) {
            $approvals = $related->approvalRequests->where('investor_agreement_id', $related->investor_agreement_id);
            $overruns = $approvals
                ->where('request_type', ApprovalRequestType::BudgetOverrun)
                ->whereIn('status', $openStatuses)
                ->when($overrunMinor > 0, fn ($requests) => $requests->where('requested_amount_minor', '!=', $overrunMinor));

            foreach ($overruns as $request) {
                if ($overrunMinor === 0) {
                    $this->supersede($team, $actor, $request);

                    continue;
                }

                $previousMinor = $request->requested_amount_minor;
                $request->update(['requested_amount_minor' => $overrunMinor]);
                $this->audit->handle(
                    team: $team, actor: $actor, action: 'approval_request.revised', subject: $request,
                    oldValues: ['requested_amount_minor' => $previousMinor],
                    newValues: ['requested_amount_minor' => $overrunMinor],
                    metadata: ['corrected_expense_id' => $expense->id],
                );
            }

            if ($overrunMinor === 0 && $overruns->isNotEmpty()) {
                $this->restoreRelatedVisibility($team, $actor, $related, $approvals);
            }
        }
    }

    /** @param Collection<int, ApprovalRequest> $approvals */
    private function restoreRelatedVisibility(Team $team, User $actor, Expense $expense, Collection $approvals): void
    {
        $remaining = $approvals
            ->where('status', '!=', ApprovalRequestStatus::Cancelled)
            ->unique('request_type')
            ->pluck('status');

        $visibility = match (true) {
            $remaining->contains(ApprovalRequestStatus::ClarificationRequested) => InvestorVisibilityStatus::ClarificationRequested,
            $remaining->contains(ApprovalRequestStatus::Pending) => InvestorVisibilityStatus::PendingApproval,
            $remaining->contains(ApprovalRequestStatus::Rejected) => InvestorVisibilityStatus::Rejected,
            $remaining->contains(ApprovalRequestStatus::Approved) => InvestorVisibilityStatus::Approved,
            default => $this->recordedVisibility($team, $expense),
        };

        $previous = $expense->investor_visibility_status;
        if ($previous !== $visibility) {
            $expense->update(['investor_visibility_status' => $visibility]);
            $this->audit->handle(
                team: $team, actor: $actor, action: 'expense.investor_visibility_reconciled', subject: $expense,
                oldValues: ['investor_visibility_status' => $previous->value],
                newValues: ['investor_visibility_status' => $visibility->value],
            );
        }
    }

    private function recordedVisibility(Team $team, Expense $expense): InvestorVisibilityStatus
    {
        $recording = $team->auditEvents()
            ->where('subject_type', $expense->getMorphClass())
            ->where('subject_id', $expense->id)
            ->where('action', 'expense.recorded')
            ->oldest('id')
            ->first(['new_values']);

        return ($recording?->new_values['investor_visibility_status'] ?? null) === InvestorVisibilityStatus::Approved->value
            ? InvestorVisibilityStatus::Approved
            : InvestorVisibilityStatus::Private;
    }

    private function supersede(Team $team, User $actor, ApprovalRequest $approval): void
    {
        $oldStatus = $approval->status->value;
        $approval->update(['status' => ApprovalRequestStatus::Cancelled]);
        $this->audit->handle(
            team: $team, actor: $actor, action: 'approval_request.superseded', subject: $approval,
            oldValues: ['status' => $oldStatus], newValues: ['status' => ApprovalRequestStatus::Cancelled->value],
        );
    }

    private function budgetOverrunMinor(Team $team, Expense $expense): int
    {
        $budgetLine = $expense->budgetLine;

        if (! $budgetLine) {
            return 0;
        }

        $spentMinor = (int) $team->expenses()
            ->where('budget_line_id', $budgetLine->id)
            ->whereIn('status', ExpenseStatus::spendableValues())
            ->sum('amount_minor');

        return max(0, $spentMinor - $budgetLine->planned_amount_minor);
    }
}
