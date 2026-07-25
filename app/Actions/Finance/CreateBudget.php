<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\BudgetStatus;
use App\Models\Budget;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateBudget
{
    public function __construct(
        private CreateBudgetLine $createBudgetLine,
        private RecordAuditEvent $recordAuditEvent,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function handle(Team $team, User $actor, array $attributes, array $lines = []): Budget
    {
        return DB::transaction(function () use ($team, $actor, $attributes, $lines) {
            $status = BudgetStatus::tryFrom((string) ($attributes['status'] ?? BudgetStatus::Draft->value)) ?? BudgetStatus::Draft;

            $budget = Budget::create([
                ...$attributes,
                'team_id' => $team->id,
                'created_by_id' => $actor->id,
                'status' => $status,
                'currency' => $this->resolveTeamFinanceCurrency->handle($team),
                'approved_by_id' => $status === BudgetStatus::Approved ? $actor->id : null,
                'approved_at' => $status === BudgetStatus::Approved ? now() : null,
            ]);

            foreach ($lines as $line) {
                $this->createBudgetLine->handle($team, $actor, $budget, $line);
            }

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'budget.created',
                subject: $budget,
                newValues: [
                    'farm_id' => $budget->farm_id,
                    'production_cycle_id' => $budget->production_cycle_id,
                    'name' => $budget->name,
                    'status' => $budget->status->value,
                    'currency' => $budget->currency,
                    'period_start_on' => $budget->period_start_on?->toDateString(),
                    'period_end_on' => $budget->period_end_on?->toDateString(),
                ],
            );

            return $budget->refresh();
        });
    }
}
