<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Teams\UpdateTeamSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\UpdateFinanceCurrencyRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FinanceSettingController extends Controller
{
    public function updateCurrency(UpdateFinanceCurrencyRequest $request, Team $currentTeam, UpdateTeamSetting $updateTeamSetting): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $updateTeamSetting->handle(
            team: $currentTeam,
            actor: $user,
            settingGroup: 'finance',
            settingKey: 'default_currency',
            value: ['currency' => $request->currency()],
            reason: 'Finance default currency changed.',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Finance currency updated.')]);

        return to_route('finance.index', ['current_team' => $currentTeam]);
    }
}
