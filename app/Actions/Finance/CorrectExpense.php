<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CorrectExpense
{
    public function __construct(
        private RecordAuditEvent $audit,
        private RequestExpenseApprovals $requestExpenseApprovals,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'amount' => ['sometimes', 'required', 'numeric', 'min:0', 'regex:/^\d{1,15}(\.\d{1,2})?$/D'],
            'description' => ['sometimes', 'required', 'string', 'max:4000'],
            'incurred_on' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'vendor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public static function isCorrectable(Expense $expense): bool
    {
        return ! in_array($expense->status, [ExpenseStatus::Reconciled, ExpenseStatus::Voided], true)
            && ! (bool) ($expense->getAttribute('inventory_movements_exists') ?? $expense->inventoryMovements()->exists())
            && ! (bool) ($expense->getAttribute('external_transfers_exists') ?? $expense->externalTransfers()->exists());
    }

    /** @param array<string, mixed> $changes */
    public function handle(Team $team, User $actor, int $expenseId, array $changes): Expense
    {
        Gate::forUser($actor)->authorize('manageFinance', $team);

        return DB::transaction(function () use ($team, $actor, $expenseId, $changes): Expense {
            $budgetLineId = $team->expenses()->whereKey($expenseId)->value('budget_line_id');
            if ($budgetLineId !== null) {
                $team->budgetLines()->lockForUpdate()->findOrFail($budgetLineId);
            }

            $expense = $team->expenses()->lockForUpdate()->findOrFail($expenseId);
            if (! self::isCorrectable($expense)) {
                throw ValidationException::withMessages(['amount' => 'Expenses linked to stock movements or external transfers, reconciled expenses, and voided expenses cannot be corrected here.']);
            }

            $reason = $changes['reason'];
            unset($changes['reason']);
            if (isset($changes['amount'])) {
                $changes['amount_minor'] = Money::toMinorUnit((string) $changes['amount']);
                unset($changes['amount']);
            }
            $expense->fill($changes);
            $updated = $expense->getDirty();
            if ($updated === []) {
                return $expense;
            }

            $before = array_intersect_key($expense->getRawOriginal(), $updated);
            $expense->save();

            $this->requestExpenseApprovals->handle($team, $actor, $expense, correcting: true);

            $this->audit->handle(
                team: $team, actor: $actor, action: 'expense.corrected', subject: $expense,
                oldValues: $before, newValues: $updated, metadata: ['reason' => $reason],
            );

            return $expense;
        });
    }
}
