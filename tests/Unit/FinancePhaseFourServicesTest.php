<?php

use App\Actions\Finance\CalculateBudgetVariance;
use App\Actions\Finance\CalculateCarryForwardBalance;
use App\Enums\BudgetStatus;
use App\Enums\ExpenseStatus;
use App\Enums\ExternalTransferDirection;
use App\Enums\ExternalTransferStatus;
use App\Enums\FundingPhaseStatus;
use App\Enums\TransferReconciliationStatus;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExternalTransfer;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FundingPhase;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\TransferReconciliation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('money helper parses formats and normalizes currency without floats', function () {
    expect(Money::toMinorUnit('1,250.50'))->toBe(125_050)
        ->and(Money::toMinorUnit('10'))->toBe(1_000)
        ->and(Money::toMinorUnit('10.5'))->toBe(1_050)
        ->and(Money::toDecimal(125_050))->toBe('1250.50')
        ->and(Money::toDecimal(-50))->toBe('-0.50')
        ->and(Money::normalizeCurrency(' usd '))->toBe('USD');

    expect(fn () => Money::toMinorUnit('10.999'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::normalizeCurrency('US'))->toThrow(InvalidArgumentException::class);
});

test('finance enum options expose labels for forms', function () {
    expect(BudgetStatus::options())->toContain(['value' => 'draft', 'label' => 'Draft'])
        ->and(FundingPhaseStatus::options())->toContain(['value' => 'released_externally', 'label' => 'Released externally'])
        ->and(ExpenseStatus::options())->toContain(['value' => 'approved', 'label' => 'Approved'])
        ->and(ExternalTransferDirection::options())->toContain(['value' => 'incoming', 'label' => 'Incoming'])
        ->and(ExternalTransferStatus::options())->toContain(['value' => 'recorded', 'label' => 'Recorded'])
        ->and(TransferReconciliationStatus::options())->toContain(['value' => 'matched', 'label' => 'Matched']);
});

test('budget variance calculates budgeted spent balance and categories', function () {
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $category = ExpenseCategory::factory()->create([
        'team_id' => $team->id,
        'name' => 'Tools',
        'slug' => 'tools',
    ]);
    $budget = Budget::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'currency' => 'NGN',
    ]);
    BudgetLine::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'budget_id' => $budget->id,
        'expense_category_id' => $category->id,
        'planned_amount_minor' => 100_000,
    ]);
    Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'expense_category_id' => $category->id,
        'amount_minor' => 25_000,
        'status' => ExpenseStatus::Approved,
    ]);
    Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'expense_category_id' => $category->id,
        'amount_minor' => 10_000,
        'status' => ExpenseStatus::Rejected,
    ]);

    $variance = app(CalculateBudgetVariance::class)->handle($team, $farm);

    expect($variance['budgetedMinor'])->toBe(100_000)
        ->and($variance['spentMinor'])->toBe(25_000)
        ->and($variance['balance'])->toBe('750.00')
        ->and($variance['variance'])->toBe('750.00')
        ->and($variance['byCategory'][0]['categoryName'])->toBe('Tools')
        ->and($variance['byCategory'][0]['spent'])->toBe('250.00');
});

test('carry-forward calculates rolling phase balances', function () {
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $category = ExpenseCategory::factory()->create(['team_id' => $team->id]);
    $first = FundingPhase::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'First',
        'externally_released_amount_minor' => 100_000,
        'expected_on' => '2026-06-01',
    ]);
    $second = FundingPhase::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Second',
        'externally_released_amount_minor' => 50_000,
        'expected_on' => '2026-06-02',
    ]);
    Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'funding_phase_id' => $first->id,
        'expense_category_id' => $category->id,
        'amount_minor' => 25_000,
        'status' => ExpenseStatus::Approved,
    ]);
    Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'funding_phase_id' => $second->id,
        'expense_category_id' => $category->id,
        'amount_minor' => 10_000,
        'status' => ExpenseStatus::Approved,
    ]);

    $carryForward = app(CalculateCarryForwardBalance::class)->handle($team, $farm);

    expect($carryForward['released'])->toBe('1500.00')
        ->and($carryForward['spent'])->toBe('350.00')
        ->and($carryForward['carryForward'])->toBe('1150.00')
        ->and($carryForward['phases'][0]['phaseName'])->toBe('First')
        ->and($carryForward['phases'][0]['carryForward'])->toBe('750.00')
        ->and($carryForward['phases'][1]['phaseName'])->toBe('Second')
        ->and($carryForward['phases'][1]['opening'])->toBe('750.00');
});

test('finance models expose casts and relationships', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $category = ExpenseCategory::factory()->create(['team_id' => $team->id]);
    $activity = FarmActivity::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
    ]);
    $budget = Budget::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'created_by_id' => $user->id,
        'approved_by_id' => $user->id,
        'status' => BudgetStatus::Approved,
        'approved_at' => now(),
    ]);
    $line = BudgetLine::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'budget_id' => $budget->id,
        'expense_category_id' => $category->id,
    ]);
    $phase = FundingPhase::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'budget_id' => $budget->id,
        'created_by_id' => $user->id,
    ]);
    $expense = Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $line->id,
        'funding_phase_id' => $phase->id,
        'expense_category_id' => $category->id,
        'farm_activity_id' => $activity->id,
        'recorded_by_id' => $user->id,
    ]);
    $transfer = ExternalTransfer::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'budget_id' => $budget->id,
        'funding_phase_id' => $phase->id,
        'expense_id' => $expense->id,
        'recorded_by_id' => $user->id,
    ]);
    $reconciliation = TransferReconciliation::factory()->create([
        'team_id' => $team->id,
        'external_transfer_id' => $transfer->id,
        'reconciled_by_id' => $user->id,
    ]);

    expect($budget->status)->toBe(BudgetStatus::Approved)
        ->and($budget->team->is($team))->toBeTrue()
        ->and($budget->farm->is($farm))->toBeTrue()
        ->and($budget->productionCycle->is($cycle))->toBeTrue()
        ->and($budget->createdBy->is($user))->toBeTrue()
        ->and($budget->approvedBy->is($user))->toBeTrue()
        ->and($budget->lines()->count())->toBe(1)
        ->and($budget->fundingPhases()->count())->toBe(1)
        ->and($budget->expenses()->count())->toBe(1)
        ->and($line->team->is($team))->toBeTrue()
        ->and($line->farm->is($farm))->toBeTrue()
        ->and($line->productionCycle->is($cycle))->toBeTrue()
        ->and($line->budget->is($budget))->toBeTrue()
        ->and($line->expenseCategory->is($category))->toBeTrue()
        ->and($line->expenses()->count())->toBe(1)
        ->and($category->team->is($team))->toBeTrue()
        ->and($category->budgetLines()->count())->toBe(1)
        ->and($category->expenses()->count())->toBe(1)
        ->and($phase->team->is($team))->toBeTrue()
        ->and($phase->farm->is($farm))->toBeTrue()
        ->and($phase->productionCycle->is($cycle))->toBeTrue()
        ->and($phase->budget->is($budget))->toBeTrue()
        ->and($phase->createdBy->is($user))->toBeTrue()
        ->and($phase->expenses()->count())->toBe(1)
        ->and($phase->externalTransfers()->count())->toBe(1)
        ->and($expense->team->is($team))->toBeTrue()
        ->and($expense->farm->is($farm))->toBeTrue()
        ->and($expense->productionCycle->is($cycle))->toBeTrue()
        ->and($expense->budget->is($budget))->toBeTrue()
        ->and($expense->budgetLine->is($line))->toBeTrue()
        ->and($expense->fundingPhase->is($phase))->toBeTrue()
        ->and($expense->expenseCategory->is($category))->toBeTrue()
        ->and($expense->farmActivity->is($activity))->toBeTrue()
        ->and($expense->recordedBy->is($user))->toBeTrue()
        ->and($transfer->team->is($team))->toBeTrue()
        ->and($transfer->farm->is($farm))->toBeTrue()
        ->and($transfer->productionCycle->is($cycle))->toBeTrue()
        ->and($transfer->budget->is($budget))->toBeTrue()
        ->and($transfer->fundingPhase->is($phase))->toBeTrue()
        ->and($transfer->expense->is($expense))->toBeTrue()
        ->and($transfer->recordedBy->is($user))->toBeTrue()
        ->and($transfer->reconciliations()->count())->toBe(1)
        ->and($reconciliation->team->is($team))->toBeTrue()
        ->and($reconciliation->externalTransfer->is($transfer))->toBeTrue()
        ->and($reconciliation->reconciledBy->is($user))->toBeTrue()
        ->and($team->budgets()->count())->toBe(1)
        ->and($team->budgetLines()->count())->toBe(1)
        ->and($team->fundingPhases()->count())->toBe(1)
        ->and($team->expenses()->count())->toBe(1)
        ->and($team->externalTransfers()->count())->toBe(1)
        ->and($team->transferReconciliations()->count())->toBe(1)
        ->and($farm->budgets()->count())->toBe(1)
        ->and($farm->fundingPhases()->count())->toBe(1)
        ->and($farm->expenses()->count())->toBe(1)
        ->and($farm->externalTransfers()->count())->toBe(1)
        ->and($cycle->budgets()->count())->toBe(1)
        ->and($cycle->fundingPhases()->count())->toBe(1)
        ->and($cycle->expenses()->count())->toBe(1)
        ->and($cycle->externalTransfers()->count())->toBe(1);
});
