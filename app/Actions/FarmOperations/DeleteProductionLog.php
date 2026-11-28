<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\ProductionLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteProductionLog
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    public function handle(Team $team, ProductionLog $log, User $actor): void
    {
        DB::transaction(function () use ($team, $log, $actor): void {
            $cycle = $log->productionCycle;
            $snapshot = [
                'log_date' => $log->log_date->toDateString(),
                'domain' => $log->domain->value,
                'mortality_count' => $log->mortality_count,
            ];

            $log->delete();

            if ($cycle !== null && $cycle->initial_stock_count !== null) {
                $losses = (int) $cycle->productionLogs()
                    ->selectRaw('COALESCE(SUM(mortality_count + cull_count + graded_out_count), 0) as total_losses')
                    ->value('total_losses');

                $cycle->update([
                    'current_stock_count' => max(0, $cycle->initial_stock_count - $losses),
                ]);
            }

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'production_log.deleted',
                subject: $log,
                oldValues: $snapshot,
            );
        });
    }
}
