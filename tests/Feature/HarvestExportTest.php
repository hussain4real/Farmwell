<?php

use App\Actions\Harvests\HarvestExport;
use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
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

test('the harvest workbook filters recorded output and preserves period end sold quantities after source changes', function () {
    $scope = ['team_id' => $this->team->id, 'farm_id' => $this->farm->id];
    $unit = ProductionUnit::factory()->create($scope);
    $cycle = ProductionCycle::factory()->create($scope);
    $commodity = Commodity::factory()->for($this->team)->create();
    $fields = ['production_unit_id' => $unit->id, 'production_cycle_id' => $cycle->id, 'commodity_id' => $commodity->id];
    $filters = [...$this->period, ...$fields];
    $harvest = fn (array $changes) => HarvestRecord::factory()->create([...$fields, ...$scope,
        'harvested_on' => '2026-07-01', 'quantity' => '12.34', 'quantity_unit' => 'kg',
        'quality_notes' => '=1+1', 'labour_cost_minor' => 1575, 'currency' => 'NGN', ...$changes]);
    $first = $harvest([]);
    $last = $harvest(['harvested_on' => '2026-07-31', 'quantity_unit' => 'bags', 'currency' => 'USD']);
    $harvest(['harvested_on' => '2026-06-30']);
    $harvest(['harvested_on' => '2026-08-01']);
    $harvest(['production_cycle_id' => null]);
    $harvest(['production_unit_id' => null]);
    $harvest(['commodity_id' => Commodity::factory()->for($this->team)->create()->id]);
    $sale = fn (array $changes) => SaleRecord::factory()->create([...$scope, 'harvest_record_id' => $first->id,
        'sold_on' => '2026-07-31', 'quantity' => '1.10', 'quantity_unit' => 'kg', ...$changes]);
    $sale([]);
    $sale(['quantity' => '2.23']);
    $sale(['sold_on' => '2026-08-01']);
    $sale(['quantity_unit' => 'bags']);

    $this->post(route('harvests.exports.store', $this->team), $filters)->assertRedirectToRoute('harvests.exports.index', $this->team);
    $artifact = ReportArtifact::sole();
    expect($artifact->filters)->toEqual($filters)->and($artifact->production_cycle_id)->toBe($cycle->id);
    $first->update(['quantity' => '999.99', 'quality_notes' => 'Revised']);
    $response = $this->get(route('harvests.exports.download', [$this->team, $artifact]))->assertOk()
        ->assertDownload('harvest-log-'.$artifact->id.'.xlsx');
    $file = tempnam(sys_get_temp_dir(), 'farmwell-harvest');
    try {
        file_put_contents($file, $response->getContent());
        $sheet = (new Xlsx)->load($file)->getActiveSheet();
        expect($sheet->getHighestDataRow())->toBe(3)
            ->and($sheet->rangeToArray('A2:H2', formatData: false)[0])->toBe([$first->id, '2026-07-01', $this->farm->name, $cycle->name, $unit->name, $commodity->name, $first->stage->label(), $first->sequence_number])
            ->and($sheet->rangeToArray('I2:P2', formatData: false)[0])->toBe([12.34, 'kg', 3.33, 9.01, 'Recorded', '=1+1', 15.75, 'NGN'])
            ->and($sheet->getCell('N2')->getDataType())->toBe('s')
            ->and($sheet->getCell('A3')->getValue())->toBe($last->id)
            ->and($sheet->rangeToArray('I3:L3', formatData: false)[0])->toBe([12.34, 'bags', 0.0, 12.34])
            ->and($sheet->getCell('P3')->getValue())->toBe('USD');
    } finally {
        unlink($file);
    }
    $this->get(route('harvests.exports.index', $this->team))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('harvests/Exports')
            ->where('farms.0.id', $this->farm->id)->where('units.0.id', $unit->id)
            ->where('cycles.0.id', $cycle->id)->where('exports.data.0.id', $artifact->id)
            ->missing('exports.data.0.document'));
});

test('harvest exports require current management access and are private to their generator and team', function () {
    $store = route('harvests.exports.store', $this->team);
    $this->post($store, $this->period)->assertRedirect();
    $report = ReportArtifact::sole();
    $download = route('harvests.exports.download', [$this->team, $report]);
    $other = User::factory()->create();
    $this->team->members()->attach($other, ['role' => TeamRole::Admin->value]);
    $other->switchTeam($this->team);
    $this->actingAs($other)->get($download)->assertNotFound();
    $this->get(route('harvests.exports.index', $this->team))->assertInertia(fn (Assert $page) => $page->has('exports.data', 0));
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($this->owner, ['role' => TeamRole::Owner->value]);
    $this->owner->switchTeam($otherTeam);
    $this->actingAs($this->owner)->get(route('harvests.exports.download', [$otherTeam, $report]))->assertNotFound();
    $investor = User::factory()->create();
    $this->team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $investor->switchTeam($this->team);
    $this->actingAs($investor)->get($download)->assertForbidden();
    $this->post($store, $this->period)->assertForbidden();
    $this->get(route('harvests.exports.index', $this->team))->assertForbidden();
});

test('invalid dates or unrelated filter ids cannot create a harvest export', function () {
    $otherFarm = Farm::factory()->create();
    $unit = ProductionUnit::factory()->for($otherFarm)->create(['team_id' => $otherFarm->team_id]);
    $cycle = ProductionCycle::factory()->for($otherFarm)->create(['team_id' => $otherFarm->team_id]);
    $commodity = Commodity::factory()->create(['team_id' => $otherFarm->team_id]);
    $this->post(route('harvests.exports.store', $this->team), [...$this->period,
        'period_end' => '2025-01-01', 'commodity_id' => $commodity->id,
        'production_unit_id' => $unit->id, 'production_cycle_id' => $cycle->id,
    ])->assertSessionHasErrors(['period_end', 'production_unit_id', 'production_cycle_id', 'commodity_id']);
    $this->post(route('harvests.exports.store', $this->team), [...$this->period, 'farm_id' => $otherFarm->id])
        ->assertSessionHasErrors('farm_id');
    expect(ReportArtifact::count())->toBe(0);
});

test('oversized harvest exports ask for narrower filters without saving a partial workbook', function () {
    $data = ['farm_id' => $this->farm->id, 'team_id' => $this->team->id, 'harvested_on' => '2026-07-01', 'commodity_id' => Commodity::factory()->for($this->team)->create()->id, 'quantity' => 1, 'quantity_unit' => 'kg', 'currency' => 'NGN'];
    foreach (array_chunk(array_fill(0, HarvestExport::MAX_ROWS + 1, $data), 100) as $chunk) {
        HarvestRecord::insert($chunk);
    }
    $this->post(route('harvests.exports.store', $this->team), $this->period)->assertSessionHasErrors('period_end');
    expect(ReportArtifact::count())->toBe(0);
});
