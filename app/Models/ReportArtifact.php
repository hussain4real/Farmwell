<?php

namespace App\Models;

use App\Jobs\GenerateReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * @property int $id
 * @property int $team_id
 * @property int $generated_by_id
 * @property int $farm_id
 * @property int|null $production_cycle_id
 * @property string $period_start
 * @property string $period_end
 * @property string $type
 * @property string $status
 * @property int|null $investor_agreement_id
 * @property array<string, mixed>|null $filters
 * @property string $document
 */
class ReportArtifact extends Model
{
    protected $fillable = [
        'team_id', 'generated_by_id', 'farm_id', 'production_cycle_id', 'type',
        'period_start', 'period_end', 'document', 'filters', 'investor_agreement_id', 'status', 'failure_message', 'generated_at',
    ];

    protected $casts = ['filters' => 'array'];

    protected $hidden = ['document'];

    /** @param array<string, mixed> $attributes */
    public static function enqueue(array $attributes): void
    {
        // The report and database queue entry must commit or roll back together.
        if (DB::connection(config('queue.connections.database.connection')) !== DB::connection()) {
            throw new LogicException('Report generation requires the database queue to share the application database.');
        }

        DB::transaction(function () use ($attributes): void {
            $report = self::query()->create([...$attributes, 'status' => 'queued', 'document' => '']);
            GenerateReport::dispatch($report->id)->onConnection('database')->beforeCommit();
        });
    }
}
