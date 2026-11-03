<?php

use App\Actions\Investors\BuildInvestorStatement;
use App\Enums\InvestorAgreementStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\TeamRole;
use App\Models\DistributionRecord;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FundingPhase;
use App\Models\HarvestRecord;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\ReportArtifact;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->team = Team::factory()->create();
    $this->investor = User::factory()->create();
    $this->team->members()->attach($this->investor, ['role' => TeamRole::Investor->value]);
    $this->investor->switchTeam($this->team);
    $this->agreement = InvestorAgreement::factory()->create([
        'team_id' => $this->team->id, 'investor_id' => $this->investor->id,
        'farm_id' => Farm::factory()->create(['team_id' => $this->team->id])->id,
        'production_cycle_id' => null, 'status' => InvestorAgreementStatus::Active,
        'currency' => 'NGN', 'internal_notes' => 'confidential terms',
    ]);
    $this->routeArgs = [$this->team, $this->agreement];
    $this->period = ['period_start' => '2026-10-01', 'period_end' => '2026-10-31'];
    $this->actingAs($this->investor);
});

test('an investor generates and retrieves a saved period statement from approved agreement records', function () {
    $cycle = ProductionCycle::factory()->create(['team_id' => $this->team->id, 'farm_id' => $this->agreement->farm_id]);
    $this->agreement->update(['production_cycle_id' => $cycle->id]);
    $scope = ['team_id' => $this->team->id, 'farm_id' => $this->agreement->farm_id,
        'investor_agreement_id' => $this->agreement->id, 'investor_visibility_status' => InvestorVisibilityStatus::Approved,
        'currency' => 'NGN', 'production_cycle_id' => $cycle->id];
    FundingPhase::factory()->create([...$scope, 'released_on' => '2026-09-30', 'externally_released_amount_minor' => 12000]);
    FundingPhase::factory()->create([...$scope, 'released_on' => '2026-10-01', 'externally_released_amount_minor' => 3000]);
    Expense::factory()->create([...$scope, 'incurred_on' => '2026-09-30', 'amount_minor' => 2000]);
    $expense = Expense::factory()->create([...$scope, 'incurred_on' => '2026-10-31', 'amount_minor' => 4200]);
    foreach ([['production_cycle_id' => null], ['currency' => 'USD'], ['investor_visibility_status' => InvestorVisibilityStatus::Private], ['investor_agreement_id' => null], ['incurred_on' => '2026-11-01']] as $excluded) {
        Expense::factory()->create([...$scope, 'incurred_on' => '2026-10-15', 'amount_minor' => 999900, ...$excluded]);
    }
    HarvestRecord::factory()->create([...$scope, 'harvested_on' => '2026-10-10', 'quantity' => '27.5', 'quantity_unit' => 'kg']);
    $sale = SaleRecord::factory()->create([...$scope, 'sold_on' => '2026-10-31', 'net_amount_minor' => 8100]);
    DistributionRecord::factory()->create([...$scope, 'sale_record_id' => $sale->id, 'calculated_at' => '2026-10-31 23:59:59', 'capital_recovered_minor' => 2900, 'investor_share_minor' => 1800]);

    $this->post(route('investor-statements.store', $this->routeArgs), $this->period)
        ->assertRedirect(route('investor-statements.index', $this->routeArgs));
    expect(ReportArtifact::sole()->status)->toBe('queued');
    $this->artisan('queue:work database --once --no-interaction')->assertExitCode(0);
    $report = ReportArtifact::sole();
    expect($report->status)->toBe('ready');
    $this->get(route('investor-statements.index', $this->routeArgs))->assertInertia(fn (Assert $page) => $page
        ->component('investor-portal/Statements')->where('statements.data.0.id', $report->id)->missing('statements.data.0.document'));
    expect(app(BuildInvestorStatement::class)->render($this->agreement, ...array_values($this->period)))->toContain('Opening funding balance</td><td>100.00', 'Funds released</td><td>30.00', 'Approved spend</td><td>42.00', 'Closing funding balance</td><td>88.00', 'Net sales</td><td>81.00', 'Capital recovered</td><td>29.00', 'Investor profit share</td><td>18.00', 'kg')
        ->not->toContain('confidential terms');
    $expense->update(['amount_minor' => 100]);
    $response = $this->get(route('investor-statements.download', [...$this->routeArgs, $report]))
        ->assertOk()->assertDownload('investor-statement-'.$report->id.'.pdf')->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-')->toBe(base64_decode($report->document));
});

test('statement access follows the current agreement and its original audience', function () {
    $this->post(route('investor-statements.store', $this->routeArgs), ['period_start' => '2026-11-01', 'period_end' => '2026-10-01'])
        ->assertSessionHasErrors('period_end');
    expect(ReportArtifact::count())->toBe(0);
    $this->post(route('investor-statements.store', $this->routeArgs), $this->period)->assertRedirect();
    expect(ReportArtifact::sole()->status)->toBe('queued');
    $this->artisan('queue:work database --once --no-interaction')->assertExitCode(0);
    $report = ReportArtifact::sole();
    expect($report->status)->toBe('ready');
    $other = User::factory()->create();
    $this->team->members()->attach($other, ['role' => TeamRole::Investor->value]);
    $other->switchTeam($this->team);
    $this->actingAs($other);
    $this->get(route('investor-statements.index', $this->routeArgs))->assertNotFound();
    $this->post(route('investor-statements.store', $this->routeArgs), $this->period)->assertNotFound();
    $url = route('investor-statements.download', [...$this->routeArgs, $report]);
    $this->get($url)->assertNotFound();
    $this->agreement->update(['investor_id' => $other->id]);
    $this->get($url)->assertNotFound();
    $this->agreement->update(['investor_id' => $this->investor->id, 'status' => InvestorAgreementStatus::Suspended]);
    $this->actingAs($this->investor)->get($url)->assertNotFound();
});
