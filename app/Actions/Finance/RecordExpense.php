<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
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
                    'expense_category_id' => $expense->expense_category_id,
                    'amount_minor' => $expense->amount_minor,
                    'currency' => $expense->currency,
                    'status' => $expense->status->value,
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

            return $expense->refresh();
        });
    }
}
