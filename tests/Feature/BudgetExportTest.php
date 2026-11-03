<?php

use App\Actions\Finance\BudgetVarianceExport;
use App\Enums\ExpenseStatus;
use App\Enums\TeamRole;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FarmActivity;
use App\Models\ReportArtifact;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

beforeEach(function () {
    $this->withoutVite();
    $this->owner = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->owner, ['role' => TeamRole::Owner->value]);
    $this->owner->switchTeam($this->team);
    $this->actingAs($this->owner);
    $this->budget = Budget::factory()->for($this->team)->create();
    $this->scope = ['team_id' => $this->team->id, 'farm_id' => $this->budget->farm_id];
    $this->period = ['period_start' => '2026-10-01', 'period_end' => '2026-10-31'];
    $this->args = [$this->team, $this->budget];
    $this->store = route('finance.budget-exports.store', $this->args);
    $this->index = route('finance.budget-exports.index', $this->args);
});

test('the saved workbook reconciles category variance to its dated expense detail', function () {
    $category = ExpenseCategory::factory()->for($this->team)->create(['name' => 'Seeds']);
    $line = BudgetLine::factory()->for($this->budget)->create(['planned_amount_minor' => 1050, 'expense_category_id' => $category->id]);
    BudgetLine::factory()->for($this->budget)->create(['planned_amount_minor' => 225, 'expense_category_id' => null]);
    BudgetLine::factory()->for($this->budget)->create(['currency' => 'USD']);
    $activity = FarmActivity::factory()->create([...$this->scope, 'activity_type' => '=SOW']);
    $expense = fn (array $changes) => Expense::factory()->create([...$this->scope, 'budget_id' => $this->budget->id,
        'expense_category_id' => $category->id, 'amount_minor' => 750, 'incurred_on' => '2026-10-01', ...$changes]);
    $first = $expense(['status' => ExpenseStatus::Draft, 'description' => '=2+3', 'notes' => 'Extra seed', 'farm_activity_id' => $activity->id]);
    $expense(['budget_id' => null, 'budget_line_id' => $line->id, 'status' => ExpenseStatus::Reconciled, 'incurred_on' => '2026-10-31']);
    $expense(['incurred_on' => '2026-09-30']);
    $expense(['incurred_on' => '2026-11-01']);
    $expense(['status' => ExpenseStatus::Rejected]);
    $expense(['currency' => 'USD']);
    $expense(['budget_id' => Budget::factory()->create($this->scope)->id]);
    $expense(['budget_id' => null, 'budget_line_id' => null]);

    $this->post($this->store, $this->period)->assertRedirect($this->index);
    $report = ReportArtifact::sole();
    expect($report->filters)->toEqual(['budget_id' => $this->budget->id, 'currency' => 'NGN']);
    $first->update(['amount_minor' => 900000]);
    $this->get($this->index)->assertOk()->assertInertia(fn (Assert $page) => $page->component('finance/BudgetExports')
        ->where('budget.id', $this->budget->id)->where('exports.data.0.id', $report->id)->missing('exports.data.0.document'));
    $response = $this->get(route('finance.budget-exports.download', [...$this->args, $report]))->assertOk()
        ->assertDownload('budget-variance-'.$report->id.'.xlsx');
    $file = tempnam(sys_get_temp_dir(), 'farmwell-budget');
    try {
        file_put_contents($file, $response->getContent());
        $sheet = (new Xlsx)->load($file)->getActiveSheet();
        expect($sheet->rangeToArray('A6:E8', formatData: false))->toBe([
            ['Seeds', 10.5, 15, -4.5, 0],
            ['Uncategorized', 2.25, 0, 2.25, 2.25],
            ['Total', 12.75, 15, -2.25, 0],
        ])->and($sheet->getHighestDataRow())->toBe(11)
            ->and($sheet->rangeToArray('A10:G10', formatData: false)[0])->toBe(['2026-10-01', 'Seeds', 7.5, 'Draft', '=2+3', 'Extra seed', '#'.$activity->id.' =SOW'])
            ->and($sheet->getCell('E10')->getDataType())->toBe('s')
            ->and($sheet->getCell('A11')->getValue())->toBe('2026-10-31');
    } finally {
        unlink($file);
    }
});

test('only the generating finance manager can retrieve a budget workbook in its original budget and team', function () {
    $this->post($this->store, $this->period)->assertRedirect($this->index);
    $report = ReportArtifact::sole();
    $route = 'finance.budget-exports.download';
    $url = route($route, [...$this->args, $report]);
    $otherBudget = Budget::factory()->create($this->scope);
    $this->get(route($route, [$this->team, $otherBudget, $report]))->assertNotFound();
    $foreignBudget = Budget::factory()->create();
    $this->post(route('finance.budget-exports.store', [$this->team, $foreignBudget]), $this->period)->assertNotFound();
    $manager = User::factory()->create();
    $this->team->members()->attach($manager, ['role' => TeamRole::Admin->value]);
    $manager->switchTeam($this->team);
    $this->actingAs($manager)->get($url)->assertNotFound();
    $this->get($this->index)->assertInertia(fn (Assert $page) => $page->has('exports.data', 0));
    $investor = User::factory()->create();
    $this->team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $investor->switchTeam($this->team);
    $this->actingAs($investor)->get($this->index)->assertForbidden();
    $this->post($this->store, $this->period)->assertForbidden();
    $this->get($url)->assertForbidden();
});

test('invalid or oversized budget exports do not save a partial result', function () {
    $this->post($this->store, [...$this->period, 'period_end' => '2026-09-30'])->assertSessionHasErrors('period_end');
    $category = ExpenseCategory::factory()->for($this->team)->create();
    $row = [...$this->scope, 'budget_id' => $this->budget->id, 'expense_category_id' => $category->id, 'amount_minor' => 150,
        'description' => 'Seed', 'status' => 'approved', 'currency' => $this->budget->currency, 'incurred_on' => '2026-10-05'];
    foreach (array_chunk(array_fill(0, BudgetVarianceExport::MAX_EXPENSES + 1, $row), 100) as $rows) {
        Expense::insert($rows);
    }
    $this->post($this->store, $this->period)->assertSessionHasErrors('period_end');
    expect(ReportArtifact::count())->toBe(0);
});
