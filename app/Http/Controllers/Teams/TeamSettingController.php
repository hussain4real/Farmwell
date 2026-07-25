<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\UpdateTeamSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamSettingRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TeamSettingController extends Controller
{
    /**
     * Update a team-scoped setting.
     */
    public function update(
        UpdateTeamSettingRequest $request,
        Team $team,
        string $settingGroup,
        string $settingKey,
        UpdateTeamSetting $updateTeamSetting,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $updateTeamSetting->handle(
            team: $team,
            actor: $user,
            settingGroup: $settingGroup,
            settingKey: $settingKey,
            value: $request->settingValue(),
            reason: $request->reason(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team setting updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
