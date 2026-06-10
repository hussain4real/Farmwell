<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Team;
use App\Models\User;

class CreateBudgetLine
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, User $actor, Budget $budget, array $attributes): BudgetLine
    {
        $line = BudgetLine::create([
            ...$attributes,
            'team_id' => $team->id,
            'farm_id' => $budget->farm_id,
            'production_cycle_id' => $budget->production_cycle_id,
            'budget_id' => $budget->id,
            'currency' => $budget->currency,
        ]);

        $this->recordAuditEvent->handle(
            team: $team,
            actor: $actor,
            action: 'budget_line.created',
            subject: $line,
            newValues: $line->only([
                'budget_id',
                'expense_category_id',
                'description',
                'planned_amount_minor',
                'currency',
            ]),
        );

        return $line;
    }
}
