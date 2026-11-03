<?php

use App\Actions\FarmOperations\ActivityLogExport;
use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FundingPhase;
use App\Models\ProductionUnit;
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
    $this->farm = Farm::factory()->for($this->team)->create();
    $this->period = ['farm_id' => $this->farm->id, 'period_start' => '2026-07-01', 'period_end' => '2026-07-31'];
});

test('activity export filters include both period boundaries and count an activity linked by multiple expenses once', function () {
    $scope = ['team_id' => $this->team->id, 'farm_id' => $this->farm->id];
    $unit = ProductionUnit::factory()->create($scope);
    $commodity = Commodity::factory()->for($this->team)->create();
    $phase = FundingPhase::factory()->create($scope);
    $fields = ['production_unit_id' => $unit->id, 'commodity_id' => $commodity->id, 'activity_type' => 'Planting'];
    $filters = [...$this->period, ...$fields, 'funding_phase_id' => $phase->id];
    $row = fn (array $changes) => FarmActivity::factory()->create([...$scope, ...$fields, 'activity_date' => '2026-07-01', ...$changes]);
    $first = $row(['description' => '=1+1', 'cost' => '9.75']);
    $last = $row(['activity_date' => '2026-07-31']);
    $row([]);
    $excluded = [
        $row(['commodity_id' => null]), $row(['production_unit_id' => null]),
        $row(['activity_type' => 'Feeding']), $row(['activity_date' => '2026-06-30']),
        $row(['activity_date' => '2026-08-01']),
    ];
    foreach ([$first, $first, $last, ...$excluded] as $activity) {
        Expense::factory()->create([...$scope, 'funding_phase_id' => $phase->id, 'farm_activity_id' => $activity->id]);
    }

    $this->post(route('activity-exports.store', $this->team), $filters)->assertRedirectToRoute('activity-exports.index', $this->team);
    $artifact = ReportArtifact::sole();
    expect($artifact->filters)->toEqual($filters);
    $first->update(['description' => 'Changed later']);
    $response = $this->get(route('activity-exports.download', [$this->team, $artifact]))->assertOk()
        ->assertDownload('activity-log-'.$artifact->id.'.xlsx');
    $file = tempnam(sys_get_temp_dir(), 'farmwell-xlsx');
    try {
        file_put_contents($file, $response->getContent());
        $sheet = (new Xlsx)->load($file)->getActiveSheet();
        expect($sheet->getHighestDataRow())->toBe(3)
            ->and($sheet->getCell('A2')->getValue())->toBe('2026-07-01')
            ->and($sheet->getCell('A3')->getValue())->toBe('2026-07-31')
            ->and($sheet->getCell('F2')->getValue())->toBe('=1+1')
            ->and($sheet->getCell('F2')->getDataType())->toBe('s')
            ->and($sheet->getCell('I2')->getValue())->toBe(9.75)
            ->and($sheet->getCell('M2')->getValue())->toBe(0);
    } finally {
        unlink($file);
    }

    $this->get(route('activity-exports.index', $this->team))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('field-diary/Exports')
            ->where('farms.0.id', $this->farm->id)->where('units.0.id', $unit->id)
            ->where('phases.0.id', $phase->id)->where('commodities.0.id', $commodity->id)
            ->has('exports.data', 1)->missing('exports.data.0.document'));
});

test('activity exports require current management access and are private to their generator and team', function () {
    $store = route('activity-exports.store', $this->team);
    $this->post($store, $this->period)->assertRedirect();
    $report = ReportArtifact::sole();
    $download = route('activity-exports.download', [$this->team, $report]);
    $other = User::factory()->create();
    $this->team->members()->attach($other, ['role' => TeamRole::Admin->value]);
    $other->switchTeam($this->team);
    $this->actingAs($other)->get($download)->assertNotFound();
    $this->get(route('activity-exports.index', $this->team))->assertInertia(fn (Assert $page) => $page->has('exports.data', 0));
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($this->owner, ['role' => TeamRole::Owner->value]);
    $this->owner->switchTeam($otherTeam);
    $this->actingAs($this->owner)->get(route('activity-exports.download', [$otherTeam, $report]))->assertNotFound();
    $investor = User::factory()->create();
    $this->team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $investor->switchTeam($this->team);
    $this->actingAs($investor)->get($download)->assertForbidden();
    $this->post($store, $this->period)->assertForbidden();
    $this->get(route('activity-exports.index', $this->team))->assertForbidden();
});

test('invalid dates or unrelated filter ids cannot create an activity export', function () {
    $otherFarm = Farm::factory()->create();
    $unit = ProductionUnit::factory()->for($otherFarm)->create(['team_id' => $otherFarm->team_id]);
    $phase = FundingPhase::factory()->for($otherFarm)->create(['team_id' => $otherFarm->team_id]);
    $commodity = Commodity::factory()->create(['team_id' => $otherFarm->team_id]);
    $this->post(route('activity-exports.store', $this->team), [...$this->period,
        'period_end' => '2025-01-01', 'commodity_id' => $commodity->id,
        'production_unit_id' => $unit->id, 'funding_phase_id' => $phase->id,
    ])->assertSessionHasErrors(['period_end', 'production_unit_id', 'funding_phase_id', 'commodity_id']);
    $this->post(route('activity-exports.store', $this->team), [...$this->period, 'farm_id' => $otherFarm->id])
        ->assertSessionHasErrors('farm_id');
    expect(ReportArtifact::count())->toBe(0);
});

test('oversized activity exports ask for narrower filters without saving a partial workbook', function () {
    $data = ['farm_id' => $this->farm->id, 'team_id' => $this->team->id, 'activity_date' => '2026-07-01', 'activity_type' => 'Planting', 'description' => 'Seeded'];
    foreach (array_chunk(array_fill(0, ActivityLogExport::MAX_ROWS + 1, $data), 100) as $chunk) {
        FarmActivity::insert($chunk);
    }
    $this->post(route('activity-exports.store', $this->team), $this->period)->assertSessionHasErrors('period_end');
    expect(ReportArtifact::count())->toBe(0);
});
