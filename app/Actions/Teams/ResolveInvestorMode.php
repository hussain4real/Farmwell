<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamSetting;

class ResolveInvestorMode
{
    public const SettingGroup = 'workspace';

    public const SettingKey = 'investor_mode';

    public function __construct(private ResolveWorkspacePlan $resolveWorkspacePlan) {}

    public function handle(Team $team): bool
    {
        if (! $this->resolveWorkspacePlan->handle($team)->allowsInvestorMode()) {
            return false;
        }

        $setting = TeamSetting::query()
            ->where('team_id', $team->id)
            ->where('setting_group', self::SettingGroup)
            ->where('setting_key', self::SettingKey)
            ->first();

        if ($setting !== null && array_key_exists('enabled', $setting->value)) {
            return (bool) $setting->value['enabled'];
        }

        return $team->investorAgreements()->exists()
            || $team->approvalRequests()->exists()
            || $team->memberships()->where('role', TeamRole::Investor->value)->exists()
            || $team->invitations()->whereNull('accepted_at')->where('role', TeamRole::Investor->value)->exists();
    }
}
