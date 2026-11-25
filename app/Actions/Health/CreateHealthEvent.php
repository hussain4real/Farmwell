<?php

namespace App\Actions\Health;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Enums\HealthEventStatus;
use App\Models\HealthEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateHealthEvent
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, User $actor, array $attributes): HealthEvent
    {
        return DB::transaction(function () use ($team, $actor, $attributes) {
            $status = $attributes['status'] ?? HealthEventStatus::Scheduled->value;
            $completedOn = $attributes['completed_on'] ?? ($status === HealthEventStatus::Completed->value ? now()->toDateString() : null);

            $event = HealthEvent::create([
                ...$attributes,
                'team_id' => $team->id,
                'recorded_by_id' => $actor->id,
                'status' => $status,
                'completed_on' => $completedOn,
                'cost_minor' => $attributes['cost_minor'] ?? 0,
                'currency' => $attributes['currency'] ?? $this->resolveTeamFinanceCurrency->handle($team),
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'health_event.created',
                subject: $event,
                newValues: [
                    'farm_id' => $event->farm_id,
                    'production_cycle_id' => $event->production_cycle_id,
                    'event_type' => $event->event_type->value,
                    'title' => $event->title,
                    'scheduled_on' => $event->scheduled_on->toDateString(),
                    'status' => $event->status->value,
                ],
            );

            return $event;
        });
    }
}
