<?php

use App\Enums\InvestorAgreementStatus;
use App\Enums\TeamRole;
use App\Jobs\GenerateReport;
use App\Models\Farm;
use App\Models\InvestorAgreement;
use App\Models\ReportArtifact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Excel;

beforeEach(function () {
    $this->withoutVite();
    $this->owner = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->owner, ['role' => TeamRole::Owner->value]);
    $this->owner->switchTeam($this->team);
    $this->actingAs($this->owner);
    $this->farm = Farm::factory()->for($this->team)->create();
    $this->period = ['farm_id' => $this->farm->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31'];
    $this->store = route('harvests.sales.exports.store', $this->team);
    $this->work = fn () => $this->artisan('queue:work database --once --no-interaction')->assertExitCode(0);
});

test('queued downloads become ready after a transient error and duplicate delivery preserves the file', function () {
    $this->post($this->store, $this->period)->assertRedirect();
    $report = ReportArtifact::sole();
    $url = route('harvests.sales.exports.download', [$this->team, $report]);
    $this->get($url)->assertNotFound();
    $this->get(route('harvests.sales.exports.index', $this->team))->assertInertia(fn (Assert $page) => $page
        ->where('exports.data.0.status', 'queued')->missing('exports.data.0.document'));
    $excel = app(Excel::class);
    $this->mock(Excel::class)->shouldReceive('raw')->once()->andThrow(new RuntimeException('transient renderer error'));
    ($this->work)();
    $job = DB::table('jobs')->sole();
    expect($report->refresh()->status)->toBe('queued')->and($job->attempts)->toBe(1)
        ->and($job->available_at)->toBeGreaterThan(now()->timestamp);
    app()->instance(Excel::class, $excel);
    $this->travel(11)->seconds();
    ($this->work)();
    expect($report->refresh()->status)->toBe('ready')->and($report->generated_at)->not->toBeNull();
    $bytes = $this->get($url)->assertOk()->getContent();
    $duplicate = new GenerateReport($report->id);
    Queue::connection('database')->push($duplicate);
    ($this->work)();
    $duplicate->failed(new RuntimeException('late failure'));
    expect($this->get($url)->assertOk()->getContent())->toBe($bytes);
});

test('worker exhaustion records a safe terminal failure and allows a fresh request', function () {
    $this->post($this->store, $this->period)->assertRedirect();
    $this->mock(Excel::class)->shouldReceive('raw')->times(3)->andThrow(new RuntimeException('private diagnostics'));
    foreach ([11, 31, 1] as $delay) {
        ($this->work)();
        $this->travel($delay)->seconds();
    }
    $report = ReportArtifact::sole();
    expect($report->status)->toBe('failed')->and($report->document)->toBe('')
        ->and($report->failure_message)->not->toContain('private diagnostics')
        ->and(DB::table('jobs')->count())->toBe(0);
    $this->get(route('harvests.sales.exports.download', [$this->team, $report]))->assertNotFound();
    $this->post($this->store, $this->period)->assertRedirect();
    expect(ReportArtifact::where('status', 'queued')->count())->toBe(1);
});

test('the worker checks finance permission again after submission', function () {
    $this->post($this->store, $this->period)->assertRedirect();
    $this->team->members()->detach($this->owner);
    ($this->work)();
    expect(ReportArtifact::sole()->status)->toBe('failed')->and(ReportArtifact::sole()->document)->toBe('');
});

test('reassignment before statement generation cannot expose the new investor data', function () {
    $investor = User::factory()->create();
    $this->team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $investor->switchTeam($this->team);
    $agreement = InvestorAgreement::factory()->create([
        'investor_id' => $investor->id, 'team_id' => $this->team->id, 'farm_id' => $this->farm->id,
        'status' => InvestorAgreementStatus::Active,
    ]);
    $this->actingAs($investor)->post(route('investor-statements.store', [$this->team, $agreement]), $this->period)->assertRedirect();
    $agreement->update(['investor_id' => $this->owner->id]);
    ($this->work)();
    expect(ReportArtifact::sole()->status)->toBe('failed')->and(ReportArtifact::sole()->document)->toBe('');
});

test('rolling back submission rolls back both the report and queue entry', function () {
    DB::beginTransaction();
    $this->post($this->store, $this->period)->assertRedirect();
    expect(DB::table('jobs')->count())->toBe(1);
    DB::rollBack();
    expect(ReportArtifact::count())->toBe(0)->and(DB::table('jobs')->count())->toBe(0);
});

test('submission rejects a database queue on a different connection', function () {
    config(['database.connections.report_test' => config('database.connections.sqlite'),
        'queue.connections.database.connection' => 'report_test']);
    expect(fn () => ReportArtifact::enqueue([]))->toThrow(LogicException::class, 'share the application database');
    expect(ReportArtifact::count())->toBe(0);
});
