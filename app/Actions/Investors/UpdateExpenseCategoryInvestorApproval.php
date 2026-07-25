<?php

namespace App\Actions\Investors;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\ExpenseCategory;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateExpenseCategoryInvestorApproval
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    public function handle(Team $team, ExpenseCategory $category, User $actor, bool $requiresApproval, ?string $reason = null): ExpenseCategory
    {
        return DB::transaction(function () use ($team, $category, $actor, $requiresApproval, $reason): ExpenseCategory {
            $oldValue = $category->requires_investor_approval;

            $category->forceFill([
                'requires_investor_approval' => $requiresApproval,
            ])->save();

            if ($oldValue !== $requiresApproval) {
                $this->recordAuditEvent->handle(
                    team: $team,
                    actor: $actor,
                    action: 'expense_category.investor_approval_updated',
                    subject: $category,
                    oldValues: [
                        'requires_investor_approval' => $oldValue,
                    ],
                    newValues: [
                        'requires_investor_approval' => $requiresApproval,
                    ],
                    reason: $reason,
                );
            }

            return $category->refresh();
        });
    }
}
