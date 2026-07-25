<?php

use App\Actions\Finance\EnsureDefaultExpenseCategories;
use App\Enums\ExternalTransferStatus;
use App\Enums\TeamRole;
use App\Enums\TransferReconciliationStatus;
use App\Models\AuditEvent;
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
use App\Models\TeamSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function farmwellFinanceContext(TeamRole $role = TeamRole::Owner): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create(['name' => fake()->unique()->company().' Finance']);

    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    $farm = Farm::factory()->create(['team_id' => $team->id, 'name' => 'Finance Farm']);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Finance Cycle',
    ]);
    app(EnsureDefaultExpenseCategories::class)->handle($team);
    $category = ExpenseCategory::query()->where('team_id', $team->id)->where('slug', 'tools')->firstOrFail();
    $activity = FarmActivity::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'activity_type' => 'Input purchase',
    ]);

    return [$user, $team, $farm, $cycle, $category, $activity];
}

function farmwellFinanceRoute(string $name, Team $team, array $parameters = []): string
{
    return route($name, ['current_team' => $team, ...$parameters]);
}

test('finance permissions expose owner and admin access while denying members', function () {
    [$owner, $team] = farmwellFinanceContext();
    [$admin, $adminTeam] = farmwellFinanceContext(TeamRole::Admin);
    [$member, $memberTeam] = farmwellFinanceContext(TeamRole::Member);

    $this
        ->actingAs($owner)
        ->get(farmwellFinanceRoute('finance.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/Index')
            ->where('currency', 'NGN')
            ->where('permissions.canViewFinance', true)
            ->where('permissions.canManageFinance', true)
            ->where('summary.budgets', 0)
        );

    $this
        ->actingAs($admin)
        ->get(farmwellFinanceRoute('finance.budgets.index', $adminTeam))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/Budgets')
            ->where('permissions.canManageFinance', true)
            ->has('categories', count(EnsureDefaultExpenseCategories::Defaults))
        );

    $this
        ->actingAs($member)
        ->get(farmwellFinanceRoute('finance.index', $memberTeam))
        ->assertForbidden();

    $this
        ->actingAs($member)
        ->post(farmwellFinanceRoute('finance.budgets.store', $memberTeam), [
            'farm_id' => Farm::query()->where('team_id', $memberTeam->id)->firstOrFail()->id,
            'name' => 'Denied budget',
        ])
        ->assertForbidden();
});

test('finance workflows create budgets funding expenses transfers reconciliation evidence and audits', function () {
    Storage::fake('farmwell_private');

    [$owner, $team, $farm, $cycle, $category, $activity] = farmwellFinanceContext();

    $this
        ->actingAs($owner)
        ->patch(farmwellFinanceRoute('finance.settings.currency.update', $team), [
            'currency' => 'USD',
        ])
        ->assertRedirect(farmwellFinanceRoute('finance.index', $team));

    expect(TeamSetting::query()->where('team_id', $team->id)->where('setting_group', 'finance')->first()?->value)
        ->toBe(['currency' => 'USD']);

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.budgets.store', $team), [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'name' => 'Cycle budget',
            'status' => 'approved',
            'lines' => [
                [
                    'expense_category_id' => $category->id,
                    'description' => 'Tools allocation',
                    'planned_amount' => '1500.50',
                ],
            ],
        ])
        ->assertRedirect(farmwellFinanceRoute('finance.budgets.index', $team));

    $budget = Budget::query()->with('lines')->firstOrFail();
    $line = $budget->lines->first();

    expect($budget->team_id)->toBe($team->id)
        ->and($budget->farm_id)->toBe($farm->id)
        ->and($budget->production_cycle_id)->toBe($cycle->id)
        ->and($budget->currency)->toBe('USD')
        ->and($line)->not->toBeNull()
        ->and($line?->planned_amount_minor)->toBe(150_050);

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.budget-lines.store', $team, ['budget' => $budget]), [
            'expense_category_id' => $category->id,
            'description' => 'More tools',
            'planned_amount' => '499.50',
        ])
        ->assertRedirect(farmwellFinanceRoute('finance.budgets.index', $team));

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.funding-phases.store', $team), [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'budget_id' => $budget->id,
            'name' => 'Phase one',
            'status' => 'released_externally',
            'planned_amount' => '2000',
            'requested_amount' => '2000',
            'approved_amount' => '2000',
            'externally_released_amount' => '1000',
            'expected_on' => '2026-06-01',
            'released_on' => '2026-06-02',
        ])
        ->assertRedirect(farmwellFinanceRoute('finance.funding-phases.index', $team));

    $phase = FundingPhase::query()->firstOrFail();

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.expenses.store', $team), [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'budget_id' => $budget->id,
            'budget_line_id' => $line?->id,
            'funding_phase_id' => $phase->id,
            'expense_category_id' => $category->id,
            'farm_activity_id' => $activity->id,
            'incurred_on' => '2026-06-03',
            'description' => 'Bought hand tools.',
            'amount' => '250.25',
            'receipt_caption' => 'Receipt',
            'receipts' => [
                UploadedFile::fake()->create('receipt.pdf', 64, 'application/pdf'),
            ],
        ])
        ->assertRedirect(farmwellFinanceRoute('finance.expenses.index', $team));

    $expense = Expense::query()->firstOrFail();
    $receipt = $expense->getFirstMedia(Expense::ReceiptsCollection);

    expect($expense->amount_minor)->toBe(25_025)
        ->and($expense->currency)->toBe('USD')
        ->and($expense->farm_activity_id)->toBe($activity->id)
        ->and($receipt)->not->toBeNull()
        ->and($receipt?->disk)->toBe('farmwell_private');

    $this
        ->actingAs($owner)
        ->get(farmwellFinanceRoute('finance.evidence.show', $team, ['media' => $receipt]))
        ->assertDownload($receipt->file_name);

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.external-transfers.store', $team), [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'budget_id' => $budget->id,
            'funding_phase_id' => $phase->id,
            'expense_id' => $expense->id,
            'direction' => 'incoming',
            'transfer_type' => 'recorded release',
            'counterparty_name' => 'External bank',
            'reference' => 'TRF-001',
            'amount' => '1000',
            'transferred_on' => '2026-06-02',
            'proof' => UploadedFile::fake()->create('proof.pdf', 64, 'application/pdf'),
        ])
        ->assertRedirect(farmwellFinanceRoute('finance.external-transfers.index', $team));

    $transfer = ExternalTransfer::query()->firstOrFail();
    $proof = $transfer->getFirstMedia(ExternalTransfer::ProofCollection);

    expect($transfer->status)->toBe(ExternalTransferStatus::ProofAttached)
        ->and($proof)->not->toBeNull()
        ->and($proof?->disk)->toBe('farmwell_private');

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.external-transfers.reconciliations.store', $team, ['external_transfer' => $transfer]), [
            'status' => TransferReconciliationStatus::Matched->value,
            'reconciled_amount' => '1000',
            'reconciled_at' => '2026-06-03 09:00:00',
        ])
        ->assertRedirect(farmwellFinanceRoute('finance.external-transfers.index', $team));

    expect($transfer->fresh()->status)->toBe(ExternalTransferStatus::Reconciled);

    $this
        ->actingAs($owner)
        ->get(farmwellFinanceRoute('finance.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/Index')
            ->where('currency', 'USD')
            ->where('summary.budgeted', '2000.00')
            ->where('summary.spent', '250.25')
            ->where('summary.released', '1000.00')
            ->where('variance.variance', '1749.75')
            ->where('carryForward.carryForward', '749.75')
            ->where('recentExpenses.0.amount', '250.25')
            ->where('recentTransfers.0.status', 'reconciled')
        );

    $this
        ->actingAs($owner)
        ->get(farmwellFinanceRoute('finance.budgets.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/Budgets')
            ->where('budgets.0.lines.0.plannedAmount', '1500.50')
            ->where('budgets.0.periodStartOn', null)
        );

    $this
        ->actingAs($owner)
        ->get(farmwellFinanceRoute('finance.funding-phases.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/FundingPhases')
            ->where('fundingPhases.0.externallyReleasedAmount', '1000.00')
            ->where('carryForward.phases.0.carryForward', '749.75')
        );

    $this
        ->actingAs($owner)
        ->get(farmwellFinanceRoute('finance.expenses.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/Expenses')
            ->where('activities.0.activityType', 'Input purchase')
            ->where('expenses.0.receipts.0.fileName', 'receipt.pdf')
            ->where('variance.byCategory.0.spent', '250.25')
        );

    $this
        ->actingAs($owner)
        ->get(farmwellFinanceRoute('finance.external-transfers.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/ExternalTransfers')
            ->where('externalTransfers.0.proof.0.fileName', 'proof.pdf')
            ->where('externalTransfers.0.reconciliations.0.reconciledAmount', '1000.00')
            ->where('expenses.0.amount', '250.25')
        );

    expect(AuditEvent::query()->where('team_id', $team->id)->pluck('action')->all())
        ->toContain(
            'team_setting.updated',
            'budget.created',
            'budget_line.created',
            'funding_phase.created',
            'expense.recorded',
            'expense_receipt.attached',
            'external_transfer.recorded',
            'external_transfer_proof.attached',
            'external_transfer.reconciled',
        );
});

test('finance records reject cross-team references and private evidence downloads', function () {
    Storage::fake('farmwell_private');

    [$owner, $team, $farm, $cycle, $category] = farmwellFinanceContext();
    [$otherOwner, $otherTeam, $otherFarm, $otherCycle, $otherCategory] = farmwellFinanceContext();

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.expenses.store', $team), [
            'farm_id' => $otherFarm->id,
            'production_cycle_id' => $otherCycle->id,
            'expense_category_id' => $otherCategory->id,
            'incurred_on' => '2026-06-04',
            'description' => 'Wrong tenant',
            'amount' => '10',
        ])
        ->assertSessionHasErrors(['farm_id', 'production_cycle_id', 'expense_category_id']);

    $expense = Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'expense_category_id' => $category->id,
    ]);
    $expense
        ->addMedia(UploadedFile::fake()->create('private-receipt.pdf', 32, 'application/pdf'))
        ->toMediaCollection(Expense::ReceiptsCollection, 'farmwell_private');
    $receipt = $expense->getFirstMedia(Expense::ReceiptsCollection);

    expect($receipt)->not->toBeNull();

    $this
        ->actingAs($otherOwner)
        ->get(farmwellFinanceRoute('finance.evidence.show', $otherTeam, ['media' => $receipt]))
        ->assertNotFound();
});

test('expense validation rejects inconsistent accounting dimensions and unsupported amounts', function () {
    [$owner, $team, $farm, $cycle, $category] = farmwellFinanceContext();
    $otherCategory = ExpenseCategory::factory()->create(['team_id' => $team->id]);
    $otherCycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $budget = Budget::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
    ]);
    $otherBudget = Budget::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
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
        'budget_id' => $otherBudget->id,
    ]);
    $payload = [
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $line->id,
        'expense_category_id' => $category->id,
        'incurred_on' => '2026-06-05',
        'description' => 'Accounting scope validation.',
        'amount' => '10.00',
    ];

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.expenses.store', $team), [
            ...$payload,
            'expense_category_id' => $otherCategory->id,
        ])
        ->assertSessionHasErrors('budget_line_id');

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.expenses.store', $team), [
            ...$payload,
            'budget_id' => $otherBudget->id,
        ])
        ->assertSessionHasErrors('budget_line_id');

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.expenses.store', $team), [
            ...$payload,
            'production_cycle_id' => $otherCycle->id,
        ])
        ->assertSessionHasErrors(['budget_id', 'budget_line_id']);

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.expenses.store', $team), [
            ...$payload,
            'funding_phase_id' => $phase->id,
        ])
        ->assertSessionHasErrors('funding_phase_id');

    foreach (['1.001', '1e3'] as $invalidAmount) {
        $this
            ->actingAs($owner)
            ->post(farmwellFinanceRoute('finance.expenses.store', $team), [
                ...$payload,
                'amount' => $invalidAmount,
            ])
            ->assertSessionHasErrors('amount');
    }

    expect(Expense::query()->where('team_id', $team->id)->exists())->toBeFalse();

    $this
        ->actingAs($owner)
        ->post(farmwellFinanceRoute('finance.expenses.store', $team), [
            'farm_id' => $farm->id,
            'expense_category_id' => $category->id,
            'incurred_on' => '2026-06-05',
            'description' => 'Farm-wide expense.',
            'amount' => '10.00',
        ])
        ->assertRedirect(farmwellFinanceRoute('finance.expenses.index', $team));

    expect(Expense::query()->where('team_id', $team->id)->count())->toBe(1);
});
