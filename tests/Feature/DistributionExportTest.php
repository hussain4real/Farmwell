<?php

use App\Actions\Harvests\DistributionExport;
use App\Enums\TeamRole;
use App\Models\DistributionRecord;
use App\Models\Farm;
use App\Models\InvestorAgreement;
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

test('distribution workbooks preserve recorded splits and total only period movements per agreement and currency', function () {
    $scope = ['team_id' => $this->team->id, 'farm_id' => $this->farm->id];
    $cycle = ProductionCycle::factory()->create($scope);
    $agreement = InvestorAgreement::factory()->create([...$scope, 'title' => '=1+1', 'amount_funded_minor' => 10000]);
    $fields = ['production_cycle_id' => $cycle->id, 'investor_agreement_id' => $agreement->id, 'status' => 'paid'];
    $record = fn (array $changes) => DistributionRecord::factory()->create([...$scope, ...$fields, 'calculated_at' => '2026-07-01 00:00:00',
        'previous_capital_recovered_minor' => 5000, 'capital_recovered_minor' => 5000, 'unrecovered_capital_minor' => 0,
        'sale_net_amount_minor' => 12500, 'gross_profit_minor' => 8000, 'net_profit_minor' => 7500,
        'investor_profit_share_percentage' => 40, 'farm_profit_share_percentage' => 60,
        'investor_share_minor' => 3000, 'farm_share_minor' => 4500, 'paid_at' => '2026-08-04 12:00:00', ...$changes]);
    $first = $record([]);
    $record(['calculated_at' => '2026-07-31 23:59:59', 'previous_capital_recovered_minor' => 10000,
        'capital_recovered_minor' => 0, 'sale_net_amount_minor' => 7500]);
    $record(['currency' => 'USD']);
    $record(['calculated_at' => '2026-06-30 23:59:59']);
    $record(['calculated_at' => '2026-08-01 00:00:00']);
    $record(['production_cycle_id' => null]);
    $record(['status' => 'acknowledged']);
    $record(['investor_agreement_id' => InvestorAgreement::factory()->create($scope)->id]);
    $filters = [...$this->period, ...$fields];
    $this->post(route('harvests.distributions.exports.store', $this->team), $filters)->assertRedirectToRoute('harvests.distributions.exports.index', $this->team);
    $report = ReportArtifact::sole();
    expect($report->filters)->toEqual($filters);
    $first->update(['investor_share_minor' => 0]);
    $agreement->update(['amount_funded_minor' => 99999, 'investor_profit_share_percentage' => 60, 'farm_profit_share_percentage' => 40]);
    $response = $this->get(route('harvests.distributions.exports.download', [$this->team, $report]))->assertOk()
        ->assertDownload('distribution-log-'.$report->id.'.xlsx');
    $file = tempnam(sys_get_temp_dir(), 'farmwell-distribution');
    try {
        file_put_contents($file, $response->getContent());
        $sheet = (new Xlsx)->load($file)->getActiveSheet();
        expect($sheet->getHighestDataRow())->toBe(9)
            ->and($sheet->rangeToArray('A3:H3', formatData: false)[0])->toEqual([$first->id, '2026-07-01 00:00:00', $this->farm->name,
                $cycle->name, $agreement->id, '=1+1', $first->sale_record_id, 'NGN'])
            ->and($sheet->rangeToArray('I3:V3', formatData: false)[0])->toEqual([
                125, 50, 50, 0, 80, 75, 40, 60, 30, 45, 80, 'Paid', null, '2026-08-04 12:00:00',
            ])
            ->and($sheet->getCell('F3')->getDataType())->toBe('s')
            ->and($sheet->getCell('S3')->getDataType())->toBe('n')
            ->and($sheet->getCell('B5')->getValue())->toBe('2026-07-31 23:59:59')
            ->and($sheet->rangeToArray('A8:J9', formatData: false))->toEqual([
                [$agreement->id, '=1+1', 100, 'NGN', 'NGN', 50, 150, 60, 90, 110],
                [$agreement->id, '=1+1', 100, 'NGN', 'USD', 50, 75, 30, 45, 80],
            ]);
    } finally {
        unlink($file);
    }
    $this->get(route('harvests.distributions.exports.index', $this->team))->assertInertia(fn (Assert $page) => $page
        ->component('harvests/Exports')->where('report', 'distributions')->has('distributionStatuses', 4)
        ->where('exports.data.0.id', $report->id)->missing('exports.data.0.document'));
});

test('distribution exports require current finance management access and are private to their generator and team', function () {
    $store = route('harvests.distributions.exports.store', $this->team);
    $this->post($store, $this->period)->assertRedirect();
    $report = ReportArtifact::sole();
    $download = route('harvests.distributions.exports.download', [$this->team, $report]);
    $this->get(route('harvests.exports.download', [$this->team, $report]))->assertNotFound();
    $other = User::factory()->create();
    $this->team->members()->attach($other, ['role' => TeamRole::Admin->value]);
    $other->switchTeam($this->team);
    $this->actingAs($other)->get($download)->assertNotFound();
    $this->get(route('harvests.distributions.exports.index', $this->team))->assertInertia(fn (Assert $page) => $page->has('exports.data', 0));
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($this->owner, ['role' => TeamRole::Owner->value]);
    $this->owner->switchTeam($otherTeam);
    $this->actingAs($this->owner)->get(route('harvests.distributions.exports.download', [$otherTeam, $report]))->assertNotFound();
    $investor = User::factory()->create();
    $this->team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $investor->switchTeam($this->team);
    $this->actingAs($investor)->get($download)->assertForbidden();
    $this->post($store, $this->period)->assertForbidden();
    $this->get(route('harvests.distributions.exports.index', $this->team))->assertForbidden();
});

test('invalid dates or unrelated filter ids cannot create a distribution export', function () {
    $otherFarm = Farm::factory()->create();
    $cycle = ProductionCycle::factory()->for($otherFarm)->create(['team_id' => $otherFarm->team_id]);
    $agreement = InvestorAgreement::factory()->create(['farm_id' => $otherFarm->id, 'team_id' => $otherFarm->team_id]);
    $this->post(route('harvests.distributions.exports.store', $this->team), [...$this->period,
        'period_end' => '2025-01-01', 'investor_agreement_id' => $agreement->id,
        'status' => 'void', 'production_cycle_id' => $cycle->id,
    ])->assertSessionHasErrors(['period_end', 'status', 'production_cycle_id', 'investor_agreement_id']);
    $this->post(route('harvests.distributions.exports.store', $this->team), [...$this->period, 'farm_id' => $otherFarm->id])
        ->assertSessionHasErrors('farm_id');
    expect(ReportArtifact::count())->toBe(0);
});

test('oversized distribution exports ask for narrower filters without saving a partial workbook', function () {
    $scope = ['team_id' => $this->team->id, 'farm_id' => $this->farm->id];
    $agreement = InvestorAgreement::factory()->create($scope);
    $sale = SaleRecord::factory()->create([...$scope, 'investor_agreement_id' => $agreement->id]);
    $data = $sale->getAttributes();
    unset($data['id']);
    foreach (array_chunk(array_fill(0, DistributionExport::MAX_ROWS, $data), 100) as $chunk) {
        SaleRecord::insert($chunk);
    }
    $distribution = DistributionRecord::factory()->make([...$scope, 'investor_agreement_id' => $agreement->id,
        'sale_record_id' => $sale->id, 'calculated_at' => '2026-07-01'])->getAttributes();
    foreach (SaleRecord::pluck('id')->chunk(100) as $ids) {
        DistributionRecord::insert($ids->map(fn ($id) => [...$distribution, 'sale_record_id' => $id])->values()->all());
    }
    $this->post(route('harvests.distributions.exports.store', $this->team), $this->period)->assertSessionHasErrors('period_end');
    expect(ReportArtifact::count())->toBe(0);
});
