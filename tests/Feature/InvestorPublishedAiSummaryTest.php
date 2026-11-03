<?php

use App\Enums\InvestorAgreementStatus;
use App\Enums\TeamRole;
use App\Models\AiSummary;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
