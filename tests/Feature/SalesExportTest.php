<?php

use App\Actions\Harvests\SalesExport;
use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\ProductionCycle;
use App\Models\ReportArtifact;
use App\Models\SaleRecord;
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
    $this->farm = Farm::factory()->for($this->team)->create();
    $this->period = ['farm_id' => $this->farm->id, 'period_start' => '2026-07-01', 'period_end' => '2026-07-31'];
});

test('the sales workbook includes dated sales and reconciles proceeds by commodity, currency and unit', function () {
    $scope = ['team_id' => $this->team->id, 'farm_id' => $this->farm->id];
    $cycle = ProductionCycle::factory()->create($scope);
    $commodity = Commodity::factory()->for($this->team)->create();
    $harvest = HarvestRecord::factory()->create([...$scope, 'commodity_id' => $commodity->id, 'harvested_on' => '2026-06-01']);
    $fields = ['commodity_id' => $commodity->id, 'production_cycle_id' => $cycle->id, 'payment_status' => 'partially_paid'];
    $filters = [...$this->period, ...$fields];
    $sale = fn (array $changes) => SaleRecord::factory()->create([...$scope, ...$fields,
        'harvest_record_id' => $harvest->id, 'sold_on' => '2026-07-01', 'quantity' => '1.25', 'quantity_unit' => 'kg',
        'currency' => 'NGN', 'unit_price_minor' => 4000, 'gross_amount_minor' => 5000, 'deduction_amount_minor' => 151,
        'net_amount_minor' => 4849, 'reference' => '00021', 'buyer_name' => '=1+1', ...$changes]);
    $first = $sale([]);
    $sale(['sold_on' => '2026-07-31']);
    $sale(['currency' => 'USD']);
    $sale(['quantity_unit' => 'bags']);
    $sale(['sold_on' => '2026-06-30']);
    $sale(['sold_on' => '2026-08-01']);
    $sale(['production_cycle_id' => null]);
    $sale(['payment_status' => 'pending']);
    $sale(['commodity_id' => Commodity::factory()->for($this->team)->create()->id]);
    $sale(['farm_id' => Farm::factory()->for($this->team)->create()->id]);

    $this->post(route('harvests.sales.exports.store', $this->team), $filters)->assertRedirectToRoute('harvests.sales.exports.index', $this->team);
    expect(ReportArtifact::sole()->status)->toBe('queued');
    $this->artisan('queue:work database --once --no-interaction')->assertExitCode(0);
    $report = ReportArtifact::sole();
    expect($report->status)->toBe('ready');
    expect($report->filters)->toEqual($filters);
    $first->update(['net_amount_minor' => 0, 'buyer_name' => 'Revised']);
    $response = $this->get(route('harvests.sales.exports.download', [$this->team, $report]))->assertOk()
        ->assertDownload('sales-log-'.$report->id.'.xlsx');
    $file = tempnam(sys_get_temp_dir(), 'farmwell-sales');
    try {
        file_put_contents($file, $response->getContent());
        $sheet = (new Xlsx)->load($file)->getActiveSheet();
        expect($sheet->getHighestDataRow())->toBe(11)
            ->and($sheet->rangeToArray('A3:G3', formatData: false)[0])->toBe([$first->id, '2026-07-01', $this->farm->name, $cycle->name, $harvest->id, $commodity->name, '=1+1'])
            ->and($sheet->rangeToArray('H3:P3', formatData: false)[0])->toEqual([1.25, 'kg', 40, 50, 1.51, 48.49, 'NGN', 'Partially paid', '00021'])
            ->and($sheet->getCell('G3')->getDataType())->toBe('s')
            ->and($sheet->getCell('P3')->getValue())->toBe('00021')
            ->and($sheet->getCell('M3')->getDataType())->toBe('n')
            ->and($sheet->getCell('B6')->getValue())->toBe('2026-07-31')
            ->and($sheet->rangeToArray('A9:G11', formatData: false))->toEqual([
                [$commodity->name, 'kg', 'NGN', 2.5, 100, 3.02, 96.98],
                [$commodity->name, 'kg', 'USD', 1.25, 50, 1.51, 48.49],
                [$commodity->name, 'bags', 'NGN', 1.25, 50, 1.51, 48.49],
            ]);
    } finally {
        unlink($file);
    }
    $this->get(route('harvests.sales.exports.index', $this->team))->assertInertia(fn (Assert $page) => $page
        ->component('harvests/Exports')->where('report', 'sales')->has('paymentStatuses', 4)
        ->where('exports.data.0.id', $report->id)->missing('exports.data.0.document'));
});

test('sales exports require current finance management access and are private to their generator and team', function () {
    $store = route('harvests.sales.exports.store', $this->team);
    $this->post($store, $this->period)->assertRedirect();
    expect(ReportArtifact::sole()->status)->toBe('queued');
    $this->artisan('queue:work database --once --no-interaction')->assertExitCode(0);
    $report = ReportArtifact::sole();
    expect($report->status)->toBe('ready');
    $download = route('harvests.sales.exports.download', [$this->team, $report]);
    $this->get(route('harvests.exports.download', [$this->team, $report]))->assertNotFound();
    $other = User::factory()->create();
    $this->team->members()->attach($other, ['role' => TeamRole::Admin->value]);
    $other->switchTeam($this->team);
    $this->actingAs($other)->get($download)->assertNotFound();
    $this->get(route('harvests.sales.exports.index', $this->team))->assertInertia(fn (Assert $page) => $page->has('exports.data', 0));
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($this->owner, ['role' => TeamRole::Owner->value]);
    $this->owner->switchTeam($otherTeam);
    $this->actingAs($this->owner)->get(route('harvests.sales.exports.download', [$otherTeam, $report]))->assertNotFound();
    $investor = User::factory()->create();
    $this->team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $investor->switchTeam($this->team);
    $this->actingAs($investor)->get($download)->assertForbidden();
    $this->post($store, $this->period)->assertForbidden();
    $this->get(route('harvests.sales.exports.index', $this->team))->assertForbidden();
});

test('invalid dates or unrelated filter ids cannot create a sales export', function () {
    $otherFarm = Farm::factory()->create();
    $cycle = ProductionCycle::factory()->for($otherFarm)->create(['team_id' => $otherFarm->team_id]);
    $commodity = Commodity::factory()->create(['team_id' => $otherFarm->team_id]);
    $this->post(route('harvests.sales.exports.store', $this->team), [...$this->period,
        'period_end' => '2025-01-01', 'commodity_id' => $commodity->id,
        'payment_status' => 'void', 'production_cycle_id' => $cycle->id,
    ])->assertSessionHasErrors(['period_end', 'payment_status', 'production_cycle_id', 'commodity_id']);
    $this->post(route('harvests.sales.exports.store', $this->team), [...$this->period, 'farm_id' => $otherFarm->id])
        ->assertSessionHasErrors('farm_id');
    expect(ReportArtifact::count())->toBe(0);
});

test('oversized sales exports ask for narrower filters without saving a partial workbook', function () {
    $harvest = HarvestRecord::factory()->create(['farm_id' => $this->farm->id, 'team_id' => $this->team->id]);
    $data = ['farm_id' => $this->farm->id, 'team_id' => $this->team->id, 'sold_on' => '2026-07-01', 'commodity_id' => $harvest->commodity_id,
        'harvest_record_id' => $harvest->id, 'buyer_name' => 'Buyer', 'quantity' => 1, 'quantity_unit' => 'kg', 'currency' => 'NGN',
        'unit_price_minor' => 100, 'gross_amount_minor' => 100, 'net_amount_minor' => 100];
    foreach (array_chunk(array_fill(0, SalesExport::MAX_ROWS + 1, $data), 100) as $chunk) {
        SaleRecord::insert($chunk);
    }
    $this->post(route('harvests.sales.exports.store', $this->team), $this->period)->assertRedirect();
    $this->artisan('queue:work database --once --no-interaction')->assertExitCode(0);
    $report = ReportArtifact::sole();
    expect($report->status)->toBe('failed')->and($report->document)->toBe('')->and($report->failure_message)->toContain('1000', 'period');
});
