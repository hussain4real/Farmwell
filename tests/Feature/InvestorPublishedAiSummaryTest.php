<?php

use App\Enums\InvestorAgreementStatus;
use App\Enums\TeamRole;
use App\Models\AiSummary;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\ReportArtifact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as StatementView;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function publishedSummaryContext(): array
{
    $team = Team::factory()->create();
    $investor = User::factory()->create();
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $investor->switchTeam($team);
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $cycle = ProductionCycle::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'investor_id' => $investor->id, 'status' => InvestorAgreementStatus::Active,
    ]);
    $activity = FarmActivity::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'investor_safe_summary' => 'Planting is complete.',
    ]);

    return [$team, $investor, $agreement, $activity];
}

function publishedSummary(InvestorAgreement $agreement, FarmActivity $activity, array $overrides = []): AiSummary
{
    $summary = AiSummary::factory()->create([
        'team_id' => $agreement->team_id,
        'investor_agreement_id' => $agreement->id,
        'status' => 'published',
        'final_text' => 'Reviewed investor update.',
        'prompt' => 'Private prompt must not be sent to the portal.',
        'generated_text' => 'Unreviewed draft must not be sent to the portal.',
    ]);
    $summary->forceFill([
        'type' => 'investor_progress',
        'published_at' => now(),
        'generation_context' => [
            'team_id' => $agreement->team_id,
            'investor_id' => $agreement->investor_id,
            'farm_id' => $agreement->farm_id,
            'production_cycle_id' => $agreement->production_cycle_id,
        ],
        'source_snapshot' => [[
            'id' => $activity->id,
            'date' => $activity->activity_date->toDateString(),
            'type' => $activity->activity_type,
            'status' => $activity->status->value,
            'summary' => $activity->investor_safe_summary,
        ]],
        ...$overrides,
    ])->save();

    return $summary;
}

test('investors see only their published reviewed progress summaries', function () {
    [$team, $investor, $agreement, $activity] = publishedSummaryContext();
    $visible = publishedSummary($agreement, $activity);
    publishedSummary($agreement, $activity, ['status' => 'approved', 'final_text' => 'Internal approved draft']);
    publishedSummary($agreement, $activity, ['type' => 'weekly', 'final_text' => 'Internal weekly summary']);
    publishedSummary($agreement, $activity, ['published_at' => null, 'final_text' => 'Unpublished record']);
    [, , $otherAgreement, $otherActivity] = publishedSummaryContext();
    publishedSummary($otherAgreement, $otherActivity, ['final_text' => 'Other team summary']);
    $otherInvestorSameTeam = User::factory()->create();
    $team->members()->attach($otherInvestorSameTeam, ['role' => TeamRole::Investor->value]);
    $otherAgreementSameTeam = InvestorAgreement::factory()->create([
        'team_id' => $team->id, 'farm_id' => $agreement->farm_id,
        'production_cycle_id' => $agreement->production_cycle_id, 'investor_id' => $otherInvestorSameTeam->id,
    ]);
    publishedSummary($otherAgreementSameTeam, $activity, ['final_text' => 'Other investor summary']);

    $this->actingAs($investor)->get(route('investor-portal.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('investor-portal/Index')
            ->has('agreements', 1)
            ->has('agreements.0.aiSummaries', 1)
            ->where('agreements.0.aiSummaries.0.id', $visible->id)
            ->where('agreements.0.aiSummaries.0.html', "<p>Reviewed investor update.</p>\n")
            ->where('agreements.0.aiSummaries.0.text', 'Reviewed investor update.'))
        ->assertDontSee('Private prompt must not be sent')
        ->assertDontSee('Unreviewed draft must not be sent')
        ->assertDontSee('Internal approved draft')
        ->assertDontSee('Internal weekly summary')
        ->assertDontSee('Unpublished record')
        ->assertDontSee('Other team summary')
        ->assertDontSee('Other investor summary');
});

test('published summaries are hidden after source revocation or audience changes', function () {
    [$team, $investor, $agreement, $activity] = publishedSummaryContext();
    publishedSummary($agreement, $activity);
    $url = route('investor-portal.index', $team);
    $assertCount = fn (int $count) => fn (Assert $page) => $page->has('agreements.0.aiSummaries', $count);

    $this->actingAs($investor)->get($url)->assertInertia($assertCount(1));
    $activity->update(['investor_safe_summary' => null]);
    $this->get($url)->assertInertia($assertCount(0));
    $activity->update(['investor_safe_summary' => 'Planting is complete.']);
    $this->get($url)->assertInertia($assertCount(1));
    $newInvestor = User::factory()->create();
    $team->members()->attach($newInvestor, ['role' => TeamRole::Investor->value]);
    $newInvestor->switchTeam($team);
    $agreement->update(['investor_id' => $newInvestor->id]);
    $this->get($url)->assertInertia(fn (Assert $page) => $page->has('agreements', 0));
    $this->actingAs($newInvestor)->get($url)->assertInertia($assertCount(0));
    $agreement->update(['investor_id' => $investor->id, 'status' => InvestorAgreementStatus::Suspended]);
    $this->actingAs($investor)->get($url)->assertInertia(fn (Assert $page) => $page->has('agreements', 0));
});

test('published summaries sharing a snapshot use one freshness query and are hidden after source changes', function () {
    [$team, $investor, $agreement, $activity] = publishedSummaryContext();
    publishedSummary($agreement, $activity);
    publishedSummary($agreement, $activity);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($investor)->get(route('investor-portal.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('agreements.0.aiSummaries', 2));

    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    expect($queries->filter(fn (string $query): bool => str_contains($query, '"activity_date", "activity_type", "status", "investor_safe_summary"'))->count())->toBe(1);

    $activity->update(['investor_safe_summary' => 'Updated after publication.']);
    $this->get(route('investor-portal.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('agreements.0.aiSummaries', 0));
});

test('two investors on the same team and farm receive only their own agreements', function () {
    [$team, $investor, $agreement] = publishedSummaryContext();
    $other = User::factory()->create();
    $team->members()->attach($other, ['role' => TeamRole::Investor->value]);
    $other->switchTeam($team);
    $otherAgreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id, 'farm_id' => $agreement->farm_id, 'production_cycle_id' => $agreement->production_cycle_id,
        'investor_id' => $other->id, 'status' => InvestorAgreementStatus::Active,
    ]);

    foreach ([[$investor, $agreement], [$other, $otherAgreement]] as [$viewer, $own]) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($viewer)->get(route('investor-portal.index', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('agreements', 1)
                ->where('agreements.0.id', $own->id)
                ->where('permissions.canViewInvestorPortal', true)
                ->where('currentTeamPermissions.canViewFinance', false));

        $membershipReads = collect(DB::getQueryLog())->where(fn (array $entry): bool => str_contains($entry['query'], 'from "team_members"'))->count();
        DB::disableQueryLog();
        expect($membershipReads)->toBeLessThan(10);
    }
});

test('queued investor statements include only the latest eligible reviewed update on request', function () {
    [$team, $investor, $agreement, $activity] = publishedSummaryContext();
    $activity->update(['activity_date' => '2026-10-01']);
    $summary = publishedSummary($agreement, $activity);
    $outside = $activity->replicate();
    $outside->activity_date = '2026-11-01';
    $outside->save();
    $mixed = publishedSummary($agreement, $outside);
    $mixed->update(['source_snapshot' => [...$summary->source_snapshot, ...$mixed->source_snapshot]]);
    publishedSummary($agreement, $activity, ['status' => 'approved']);
    [, , $otherAgreement, $otherActivity] = publishedSummaryContext();
    publishedSummary($otherAgreement, $otherActivity);

    $period = ['period_start' => '2026-10-01', 'period_end' => '2026-10-31'];
    $store = route('investor-statements.store', [$team, $agreement]);
    $rendered = [];
    View::composer('reports.investor-statement', function (StatementView $view) use (&$rendered): void {
        $rendered[] = $view->getData()['summary'];
    });
    $this->actingAs($investor)->post($store, [...$period, 'include_summary' => 'invalid'])->assertSessionHasErrors('include_summary');
    $this->post($store, $period)->assertRedirect();
    $this->artisan('queue:work database --once --no-interaction')->assertExitCode(0);
    $plain = ReportArtifact::sole();
    $this->post($store, [...$period, 'include_summary' => '1'])->assertRedirect();
    $this->artisan('queue:work database --once --no-interaction')->assertExitCode(0);
    $report = ReportArtifact::latest('id')->firstOrFail();
    expect($rendered)->toBe([null, $summary->final_text])
        ->and($report->filters['summary_id'])->toBe($summary->id);
    $download = route('investor-statements.download', [$team, $agreement, $report]);
    expect($this->get($download)->assertOk()->getContent())->toStartWith('%PDF-');
    $summary->update(['final_text' => 'Changed publication']);
    $this->get($download)->assertNotFound();
    $summary->update(['final_text' => 'Reviewed investor update.', 'status' => 'approved']);
    $this->get($download)->assertNotFound();
    $summary->update(['status' => 'published']);
    $this->post($store, [...$period, 'include_summary' => true])->assertRedirect();
    $activity->update(['investor_safe_summary' => null]);
    $this->artisan('queue:work database --once --no-interaction')->assertExitCode(0);
    expect($rendered)->toBe([null, 'Reviewed investor update.', null]);
    $this->get($download)->assertNotFound();
    $this->get(route('investor-statements.download', [$team, $agreement, $plain]))->assertOk();
});
