<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Investors\CreateApprovalRequest;
use App\Actions\Investors\DetermineInvestorApprovalRequirement;
use App\Enums\ApprovalRequestType;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RecordExpense
{
    public function __construct(
        private AttachFinanceEvidence $attachFinanceEvidence,
        private CreateApprovalRequest $createApprovalRequest,
        private DetermineInvestorApprovalRequirement $determineInvestorApprovalRequirement,
        private RecordAuditEvent $recordAuditEvent,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $receipts
     */
    public function handle(Team $team, User $actor, array $attributes, array $receipts = [], ?string $caption = null): Expense
    {
        return DB::transaction(function () use ($team, $actor, $attributes, $receipts, $caption) {
            $expense = Expense::create([
                ...$attributes,
                'team_id' => $team->id,
                'recorded_by_id' => $actor->id,
                'status' => ExpenseStatus::tryFrom((string) ($attributes['status'] ?? ExpenseStatus::Approved->value)) ?? ExpenseStatus::Approved,
                'currency' => $this->resolveTeamFinanceCurrency->handle($team),
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'expense.recorded',
                subject: $expense,
                newValues: [
                    'farm_id' => $expense->farm_id,
                    'production_cycle_id' => $expense->production_cycle_id,
                    'budget_id' => $expense->budget_id,
                    'budget_line_id' => $expense->budget_line_id,
                    'funding_phase_id' => $expense->funding_phase_id,
                    'investor_agreement_id' => $expense->investor_agreement_id,
                    'expense_category_id' => $expense->expense_category_id,
                    'amount_minor' => $expense->amount_minor,
                    'currency' => $expense->currency,
                    'status' => $expense->status->value,
                    'investor_visibility_status' => $expense->investor_visibility_status->value,
                ],
            );

            if ($receipts !== []) {
                $this->attachFinanceEvidence->handle($expense, $receipts, Expense::ReceiptsCollection, $actor, $caption);

                $this->recordAuditEvent->handle(
                    team: $team,
                    actor: $actor,
                    action: 'expense_receipt.attached',
                    subject: $expense,
                    newValues: [
                        'receipt_count' => count($receipts),
                    ],
                );
            }

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

                if ($requirement['required']) {
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
                        comment: $requirement['reason'],
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

            return $expense->refresh();
        });
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
