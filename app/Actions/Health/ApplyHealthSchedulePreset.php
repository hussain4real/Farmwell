<?php

namespace App\Actions\Health;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Enums\HealthEventStatus;
use App\Models\HealthEvent;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use App\Support\HealthScheduleTemplates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApplyHealthSchedulePreset
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
    ) {
        //
    }

    /**
     * @return Collection<int, HealthEvent>
     */
    public function handle(Team $team, ProductionCycle $cycle, User $actor, string $presetKey): Collection
    {
        $template = HealthScheduleTemplates::find($presetKey);
        assert($template !== null);

        return DB::transaction(function () use ($team, $cycle, $actor, $template, $presetKey): Collection {
            $currency = $this->resolveTeamFinanceCurrency->handle($team);
            $baseDate = ($cycle->actual_start_on ?? $cycle->planned_start_on)->copy();
            $created = collect();

            foreach ($template['events'] as $item) {
                $scheduledOn = $baseDate->copy()->addDays($item['day_offset'])->toDateString();

                $exists = HealthEvent::query()
                    ->where('team_id', $team->id)
                    ->where('production_cycle_id', $cycle->id)
                    ->where('title', $item['title'])
                    ->whereDate('scheduled_on', $scheduledOn)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $created->push(HealthEvent::create([
                    'team_id' => $team->id,
                    'farm_id' => $cycle->farm_id,
                    'production_cycle_id' => $cycle->id,
                    'recorded_by_id' => $actor->id,
                    'event_type' => $item['event_type'],
                    'title' => $item['title'],
                    'product_name' => $item['product_name'],
                    'administration_route' => $item['administration_route'],
                    'scheduled_on' => $scheduledOn,
                    'withdrawal_days' => $item['withdrawal_days'],
                    'status' => HealthEventStatus::Scheduled->value,
                    'cost_minor' => 0,
                    'currency' => $currency,
                    'notes' => $item['notes'],
                ]));
            }

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'health_schedule.applied',
                subject: $cycle,
                newValues: [
                    'preset' => $presetKey,
                    'created_count' => $created->count(),
                ],
            );

            return $created;
        });
    }
}
