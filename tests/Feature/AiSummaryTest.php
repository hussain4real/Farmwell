<?php

use App\Ai\Agents\InvestorProgressSummary;
use App\Enums\InvestorAgreementStatus;
use App\Enums\TeamRole;
use App\Jobs\GenerateAiSummary;
use App\Models\AiSummary;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use App\Support\SummaryMarkdown;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    $this->withoutVite();
});

function aiSummaryContext(): array
{
    $team = Team::factory()->create();
    $owner = User::factory()->create();
    $investor = User::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $owner->switchTeam($team);
    $investor->switchTeam($team);
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $cycle = ProductionCycle::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'investor_id' => $investor->id,
    ]);

    return [$team, $owner, $investor, $farm, $cycle, $agreement];
}

test('authorized users queue summaries only for their own team agreements', function () {
    Queue::fake();
    [$team, $owner, $investor, , , $agreement] = aiSummaryContext();
    $otherAgreement = InvestorAgreement::factory()->create();

    $this->actingAs($investor)->post(route('ai-summaries.store', $team), ['agreement_id' => $agreement->id])->assertForbidden();
    $this->actingAs($owner)->post(route('ai-summaries.store', $team), ['agreement_id' => $otherAgreement->id])->assertSessionHasErrors('agreement_id');
    $this->actingAs($owner)->post(route('ai-summaries.store', $team), ['agreement_id' => $agreement->id])->assertRedirect();

    $summary = AiSummary::query()->sole();
    expect($summary->team_id)->toBe($team->id)
        ->and($summary->investor_agreement_id)->toBe($agreement->id)
        ->and($summary->requested_by_id)->toBe($owner->id)
        ->and($summary->status)->toBe('queued');
    Queue::assertPushed(GenerateAiSummary::class, fn (GenerateAiSummary $job): bool => $job->summaryId === $summary->id);
});

test('generation sends only scoped investor safe fields and stores a draft', function () {
    [$team, $owner, , $farm, $cycle, $agreement] = aiSummaryContext();
    $safe = FarmActivity::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'investor_safe_summary' => 'Maize planting completed', 'description' => 'private description', 'internal_notes' => 'secret internal note',
    ]);
    FarmActivity::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'investor_safe_summary' => null, 'description' => 'unapproved activity',
    ]);
    $otherFarm = Farm::factory()->create(['team_id' => $team->id]);
    FarmActivity::factory()->create([
        'team_id' => $team->id, 'farm_id' => $otherFarm->id, 'investor_safe_summary' => 'other farm information',
    ]);
    $otherCycle = ProductionCycle::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);
    FarmActivity::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $otherCycle->id,
        'investor_safe_summary' => 'other cycle information',
    ]);
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => $agreement->id, 'requested_by_id' => $owner->id,
    ]);
    InvestorProgressSummary::fake(['Generated draft']);

    (new GenerateAiSummary($summary->id))->handle();

    InvestorProgressSummary::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Maize planting completed')
        && ! $prompt->contains('private description') && ! $prompt->contains('secret internal note')
        && ! $prompt->contains('unapproved activity') && ! $prompt->contains('other farm information')
        && ! $prompt->contains('other cycle information'));
    expect($summary->fresh()->status)->toBe('draft')
        ->and($summary->fresh()->generated_text)->toBe('Generated draft')
        ->and($summary->fresh()->source_activity_ids)->toBe([$safe->id])
        ->and($summary->fresh()->final_text)->toBeNull();
});

test('only an authorized reviewer can approve a draft and other teams cannot access it', function () {
    [$team, $owner, $investor, , , $agreement] = aiSummaryContext();
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => $agreement->id, 'status' => 'draft', 'generated_text' => 'Draft text',
    ]);
    [$otherTeam, $otherOwner] = aiSummaryContext();
    $url = route('ai-summaries.review', ['current_team' => $team, 'ai_summary' => $summary->id]);

    $this->actingAs($investor)->patch($url, ['action' => 'approve', 'final_text' => 'Reviewed'])->assertForbidden();
    $this->actingAs($otherOwner)->patch(route('ai-summaries.review', ['current_team' => $otherTeam, 'ai_summary' => $summary->id]), ['action' => 'approve', 'final_text' => 'Reviewed'])->assertNotFound();
    $this->actingAs($owner)->patch($url, ['action' => 'approve', 'final_text' => 'Reviewed text'])->assertRedirect();

    expect($summary->fresh()->status)->toBe('approved')
        ->and($summary->fresh()->final_text)->toBe('Reviewed text')
        ->and($summary->fresh()->reviewed_by_id)->toBe($owner->id)
        ->and($summary->fresh()->reviewed_at)->not->toBeNull();
    $this->patch($url, ['action' => 'reject'])->assertSessionHasErrors('action');
});

test('failed generation is recorded and cannot be reviewed or sent to the model', function () {
    [$team, $owner, , , , $agreement] = aiSummaryContext();
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => $agreement->id, 'requested_by_id' => $owner->id,
    ]);
    InvestorProgressSummary::fake();
    $job = new GenerateAiSummary($summary->id);

    try {
        $job->handle();
        test()->fail('Generation without eligible activities should fail.');
    } catch (RuntimeException $exception) {
        $job->failed($exception);
    }

    InvestorProgressSummary::assertNeverPrompted();
    expect($summary->fresh()->status)->toBe('failed')
        ->and($summary->fresh()->generated_text)->toBeNull();
    $this->actingAs($owner)->patch(route('ai-summaries.review', ['current_team' => $team, 'ai_summary' => $summary->id]), [
        'action' => 'approve', 'final_text' => 'Should not be approved',
    ])->assertSessionHasErrors('action');
});

test('investors cannot view the internal summary page and teams see only their own drafts', function () {
    [$team, $owner, $investor, , , $agreement] = aiSummaryContext();
    AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => $agreement->id, 'status' => 'draft', 'generated_text' => 'Our draft',
    ]);
    AiSummary::factory()->create(['status' => 'draft', 'generated_text' => 'Other team secret']);

    $this->actingAs($investor)->get(route('ai-summaries.index', $team))->assertForbidden();
    $this->actingAs($owner)->get(route('ai-summaries.index', $team))
        ->assertOk()->assertSee('Our draft')->assertDontSee('Other team secret');
});

test('a completed job does not regenerate or overwrite its reviewed result', function () {
    [$team, $owner, , , , $agreement] = aiSummaryContext();
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => $agreement->id, 'requested_by_id' => $owner->id,
        'status' => 'approved', 'generated_text' => 'Draft', 'final_text' => 'Reviewed', 'reviewed_by_id' => $owner->id,
    ]);
    InvestorProgressSummary::fake();

    (new GenerateAiSummary($summary->id))->handle();

    InvestorProgressSummary::assertNeverPrompted();
    expect($summary->fresh()->status)->toBe('approved')
        ->and($summary->fresh()->final_text)->toBe('Reviewed')
        ->and($summary->team->is($team))->toBeTrue()
        ->and($summary->investorAgreement->is($agreement))->toBeTrue()
        ->and($summary->requestedBy->is($owner))->toBeTrue()
        ->and($summary->reviewedBy->is($owner))->toBeTrue();
});

test('a queued job rejects an agreement that became ineligible', function () {
    [$team, $owner, , , , $agreement] = aiSummaryContext();
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => $agreement->id, 'requested_by_id' => $owner->id,
    ]);
    $agreement->update(['status' => InvestorAgreementStatus::Suspended]);
    InvestorProgressSummary::fake();
    $job = new GenerateAiSummary($summary->id);

    try {
        $job->handle();
        test()->fail('An ineligible agreement should not be summarized.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('no longer eligible');
        $job->failed($exception);
    }

    InvestorProgressSummary::assertNeverPrompted();
    expect($summary->fresh()->status)->toBe('failed');
});

test('summary markdown renders formatting while stripping executable content', function () {
    $html = SummaryMarkdown::render("## Field update\n\n**Ready** [unsafe](javascript:alert(1)) <script>alert('x')</script>");

    expect($html)->toContain('<h2>Field update</h2>')->toContain('<strong>Ready</strong>')
        ->not->toContain('<script>')->not->toContain('href="javascript:');
});
