<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $team_id
 * @property int $generated_by_id
 * @property int $farm_id
 * @property int|null $production_cycle_id
 * @property string $period_start
 * @property string $period_end
 * @property string $document
 */
class ReportArtifact extends Model
{
    protected $fillable = [
        'team_id', 'generated_by_id', 'farm_id', 'production_cycle_id', 'type',
        'period_start', 'period_end', 'document', 'filters',
    ];

    protected $casts = ['filters' => 'array'];

    protected $hidden = ['document'];
}
