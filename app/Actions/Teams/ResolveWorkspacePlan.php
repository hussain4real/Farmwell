<?php

namespace App\Actions\Teams;

use App\Enums\WorkspacePlan;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;

class ResolveWorkspacePlan
{
    public const SettingGroup = 'workspace';

    public const SettingKey = 'plan';

    /**
     * Resolve the active plan for the workspace.
     * Existing workspaces without an explicit setting default to Investor / Pilot.
     */
    public function handle(Team $team): WorkspacePlan
    {
        if ($team->relationLoaded('settings')) {
            $setting = $team->settings
                ->first(static fn (TeamSetting $item): bool => $item->setting_group === self::SettingGroup && $item->setting_key === self::SettingKey);
        } else {
            $setting = TeamSetting::query()
                ->where('team_id', $team->id)
                ->where('setting_group', self::SettingGroup)
                ->where('setting_key', self::SettingKey)
                ->first();
        }

        if ($setting !== null && is_string($setting->value['plan'] ?? null)) {
            return WorkspacePlan::tryFrom($setting->value['plan']) ?? WorkspacePlan::Investor;
        }

        return WorkspacePlan::Investor;
    }

    public function setPlan(Team $team, WorkspacePlan $plan, ?User $actor = null): void
    {
        TeamSetting::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'setting_group' => self::SettingGroup,
                'setting_key' => self::SettingKey,
            ],
            [
                'value' => ['plan' => $plan->value],
                'updated_by' => $actor?->id,
            ],
        );

        $team->unsetRelation('settings');
    }

    public function usedSeatCount(Team $team): int
    {
        $members = $team->memberships()->count();
        $pendingInvitations = $team->invitations()
            ->whereNull('accepted_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        return $members + $pendingInvitations;
    }

    public function canAddFarm(Team $team): bool
    {
        $limit = $this->handle($team)->maxFarms();

        return $limit === null || $team->farms()->count() < $limit;
    }

    public function canAddMember(Team $team): bool
    {
        $limit = $this->handle($team)->maxMembers();

        return $limit === null || $this->usedSeatCount($team) < $limit;
    }
}
