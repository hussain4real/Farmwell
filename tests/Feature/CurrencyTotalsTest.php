<?php

use App\Actions\Finance\BuildFinancePageData;
use App\Actions\Harvests\BuildHarvestPageData;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FundingPhase;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\TeamSetting;

test('dashboard totals include only the team default currency', function () {
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    TeamSetting::query()->create(['team_id' => $team->id, 'setting_group' => 'finance', 'setting_key' => 'default_currency', 'value' => ['currency' => 'NGN']]);
    foreach (['NGN' => 10000, 'USD' => 50000] as $currency => $amount) {
        $budget = Budget::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'currency' => $currency]);
        BudgetLine::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'budget_id' => $budget->id, 'currency' => $currency, 'planned_amount_minor' => $amount]);
        Expense::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'currency' => $currency, 'amount_minor' => $amount]);
        FundingPhase::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'currency' => $currency, 'externally_released_amount_minor' => $amount]);
        SaleRecord::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'currency' => $currency, 'net_amount_minor' => $amount]);
    }
    $finance = app(BuildFinancePageData::class)->dashboardSummary($team);
    $harvest = app(BuildHarvestPageData::class)->dashboardSummary($team);
    expect($finance['currency'])->toBe('NGN')
        ->and($finance['budgetedMinor'])->toBe(10000)
        ->and($finance['spentMinor'])->toBe(10000)
        ->and($finance['releasedMinor'])->toBe(10000)
        ->and($finance['variance']['budgetedMinor'])->toBe(10000)
        ->and($finance['carryForward']['releasedMinor'])->toBe(10000)
        ->and($harvest['currency'])->toBe('NGN')
        ->and($harvest['saleNetMinor'])->toBe(10000);
});
