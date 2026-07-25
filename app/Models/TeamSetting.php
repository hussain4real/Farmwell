<?php

namespace App\Models;

use Database\Factories\TeamSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property string $setting_group
 * @property string $setting_key
 * @property array<string, mixed> $value
 * @property int|null $updated_by
 * @property-read Team $team
 * @property-read User|null $updatedBy
 */
#[Fillable(['team_id', 'setting_group', 'setting_key', 'value', 'updated_by'])]
class TeamSetting extends Model
{
    /** @use HasFactory<TeamSettingFactory> */
    use HasFactory;

    /**
     * Get the team that owns the setting.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who last updated the setting.
     *
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
