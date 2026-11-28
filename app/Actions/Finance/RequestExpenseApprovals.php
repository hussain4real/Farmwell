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
                : $this->budgetOverrunMinor($team, $expense);
            $applies = $type === ApprovalRequestType::Expense || $amountMinor > 0;
            $existing = $approvals->where('request_type', $type);
            $open = $applies ? $this->openApproval($existing, $agreement->id) : null;

            foreach ($existing->except($open?->id) as $approval) {
                $oldStatus = $approval->status->value;
                $approval->update(['status' => ApprovalRequestStatus::Cancelled]);
                $this->audit->handle(
                    team: $team, actor: $actor, action: 'approval_request.superseded', subject: $approval,
                    oldValues: ['status' => $oldStatus], newValues: ['status' => ApprovalRequestStatus::Cancelled->value],
                );
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
