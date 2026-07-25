<?php

namespace App\Actions\Teams;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateTeamSetting
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * Update a team setting and audit the change.
     *
     * @param  array<string, mixed>  $value
     */
    public function handle(
        Team $team,
        User $actor,
        string $settingGroup,
        string $settingKey,
        array $value,
        ?string $reason = null,
    ): TeamSetting {
        return DB::transaction(function () use ($team, $actor, $settingGroup, $settingKey, $value, $reason) {
            $setting = TeamSetting::query()
                ->where('team_id', $team->id)
                ->where('setting_group', $settingGroup)
                ->where('setting_key', $settingKey)
                ->lockForUpdate()
                ->first();

            $oldValue = $setting?->value;

            $setting ??= new TeamSetting([
                'team_id' => $team->id,
                'setting_group' => $settingGroup,
                'setting_key' => $settingKey,
            ]);

            $setting->fill([
                'value' => $value,
                'updated_by' => $actor->id,
            ])->save();

            if ($oldValue !== $value) {
                $this->recordAuditEvent->handle(
                    team: $team,
                    actor: $actor,
                    action: 'team_setting.updated',
                    subject: $setting,
                    oldValues: [
                        'value' => $oldValue,
                    ],
                    newValues: [
                        'setting_group' => $settingGroup,
                        'setting_key' => $settingKey,
                        'value' => $value,
                    ],
                    reason: $reason,
                );
            }

            return $setting;
        });
    }
}
