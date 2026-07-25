<?php

namespace App\Actions\Finance;

use App\Models\Team;
use App\Models\TeamSetting;
use App\Support\Money;

class ResolveTeamFinanceCurrency
{
    public function handle(Team $team): string
    {
        $setting = TeamSetting::query()
            ->where('team_id', $team->id)
            ->where('setting_group', 'finance')
            ->where('setting_key', 'default_currency')
            ->first();

        $currency = is_array($setting?->value) ? ($setting->value['currency'] ?? null) : null;

        return Money::normalizeCurrency(is_string($currency) ? $currency : null);
    }
}
