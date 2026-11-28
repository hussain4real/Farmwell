<?php

namespace App\Actions\Finance;

use App\Actions\Investors\CreateApprovalRequest;
use App\Actions\Investors\DetermineInvestorApprovalRequirement;
use App\Enums\ApprovalRequestType;
use App\Enums\ExpenseStatus;
use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\Team;
use App\Models\User;

class RequestExpenseApprovals
{
    public function __construct(
        private CreateApprovalRequest $createApprovalRequest,
        private DetermineInvestorApprovalRequirement $determineInvestorApprovalRequirement,
    ) {}

    public function handle(Team $team, User $actor, Expense $expense, bool $returnForReview = false, ?ApprovalRequest $openExpenseApproval = null): void
    {
        $agreement = $expense->investorAgreement;
        if ($agreement) {
            $requirement = $this->determineInvestorApprovalRequirement->handle(
                team: $team,
                agreement: $agreement,
                requestType: ApprovalRequestType::Expense,
                amountMinor: $expense->amount_minor,
                category: $expense->expenseCategory,
                fundingPhase: $expense->fundingPhase,
                farmType: $expense->farm->farm_type->value,
            );

            if ($openExpenseApproval !== null) {
                $openExpenseApproval->update(['requested_amount_minor' => $expense->amount_minor]);
            } elseif ($requirement['required'] || $returnForReview) {
                $this->createApprovalRequest->handle(
                    team: $team,
                    agreement: $agreement,
                    actor: $actor,
                    subject: $expense,
                    requestType: ApprovalRequestType::Expense,
                    triggerType: $requirement['triggerType'],
                    requestedAmountMinor: $expense->amount_minor,
                    currency: $expense->currency,
                    rule: $requirement['rule'],
                    category: $expense->expenseCategory,
                    thresholdAmountMinor: $requirement['thresholdAmountMinor'],
                    comment: $requirement['required'] ? $requirement['reason'] : 'A corrected expense requires investor review.',
                );
            }

            $overrunMinor = $this->budgetOverrunMinor($team, $expense);

            if ($overrunMinor > 0) {
                $requirement = $this->determineInvestorApprovalRequirement->handle(
                    team: $team,
                    agreement: $agreement,
                    requestType: ApprovalRequestType::BudgetOverrun,
                    amountMinor: $overrunMinor,
                    category: $expense->expenseCategory,
                    fundingPhase: $expense->fundingPhase,
                    farmType: $expense->farm->farm_type->value,
                );

                $this->createApprovalRequest->handle(
                    team: $team,
                    agreement: $agreement,
                    actor: $actor,
                    subject: $expense,
                    requestType: ApprovalRequestType::BudgetOverrun,
                    triggerType: $requirement['triggerType'],
                    requestedAmountMinor: $overrunMinor,
                    currency: $expense->currency,
                    rule: $requirement['rule'],
                    category: $expense->expenseCategory,
                    thresholdAmountMinor: $requirement['thresholdAmountMinor'],
                    comment: $requirement['reason'],
                );
            }
        }
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
