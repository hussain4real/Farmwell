<?php

use App\Ai\Agents\InvestorProgressSummary;
use App\Enums\InvestorAgreementStatus;
use App\Enums\TeamRole;
use App\Jobs\GenerateAiSummary;
use App\Models\AiSummary;
use App\Models\AuditEvent;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\Response;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function publicationContext(): array
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
        'team_id' => $team->id, 'investor_id' => $investor->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
    ]);
    $activity = FarmActivity::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'investor_safe_summary' => 'The maize was planted.',
    ]);
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => $agreement->id,
        'requested_by_id' => $owner->id, 'type' => 'investor_progress',
    ]);
    InvestorProgressSummary::fake(['Generated private draft.']);
    (new GenerateAiSummary($summary->id))->handle();

    return [$team, $owner, $investor, $agreement, $activity, $summary->fresh()];
}

function publicationRoute(string $name, Team $team, AiSummary $summary): string
{
    return route('ai-summaries.'.$name, ['current_team' => $team, 'ai_summary' => $summary->id]);
}

function approvePublicationDraft(Team $team, User $owner, AiSummary $summary): void
{
    test()->actingAs($owner)->patch(publicationRoute('review', $team, $summary), [
        'action' => 'approve', 'final_text' => 'Reviewed and approved progress.',
    ])->assertRedirect()->assertSessionHasNoErrors();
}

test('approval remains private until explicit publication and can be withdrawn', function () {
    $this->app->instance(Gateway::class, new class implements Gateway
    {
        public function dispatch(array $page): ?Response
        {
            return new Response('', '<div>'.json_encode($page, JSON_THROW_ON_ERROR).'</div>');
        }
    });

    [$team, $owner, $investor, , , $summary] = publicationContext();
    approvePublicationDraft($team, $owner, $summary);

    $this->actingAs($investor)->get(route('investor-portal.index', $team))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('investor-portal/Index')
        ->has('agreements.0.aiSummaries', 0));
    $this->actingAs($owner)->patch(publicationRoute('publish', $team, $summary))
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($summary->fresh()->status)->toBe('published')
        ->and($summary->fresh()->published_by_id)->toBe($owner->id)
        ->and($summary->fresh()->published_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_events', ['team_id' => $team->id, 'actor_id' => $owner->id, 'action' => 'ai_summary.published', 'subject_id' => $summary->id]);
    $this->actingAs($investor)->get(route('investor-portal.index', $team))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('investor-portal/Index')
        ->has('agreements.0.aiSummaries', 1)
        ->where('agreements.0.aiSummaries.0.text', 'Reviewed and approved progress.')
        ->where('agreements.0.aiSummaries.0.html', "<p>Reviewed and approved progress.</p>\n")
        ->missing('agreements.0.aiSummaries.0.generated_text'));

    $this->actingAs($owner)->patch(publicationRoute('withdraw', $team, $summary))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($summary->fresh()->status)->toBe('approved');
    $this->assertDatabaseHas('audit_events', ['team_id' => $team->id, 'actor_id' => $owner->id, 'action' => 'ai_summary.withdrawn', 'subject_id' => $summary->id]);
    $this->actingAs($investor)->get(route('investor-portal.index', $team))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('investor-portal/Index')
        ->has('agreements.0.aiSummaries', 0));
});

test('only team managers may publish reviewed progress for their team', function () {
    [$team, $owner, $investor, , , $summary] = publicationContext();
    $otherTeam = Team::factory()->create();
    $otherOwner = User::factory()->create();
    $otherTeam->members()->attach($otherOwner, ['role' => TeamRole::Owner->value]);
    $otherOwner->switchTeam($otherTeam);
    $this->actingAs($owner)->patch(publicationRoute('publish', $team, $summary))->assertSessionHasErrors();
    approvePublicationDraft($team, $owner, $summary);

    $this->actingAs($investor)->patch(publicationRoute('publish', $team, $summary))->assertForbidden();
    $this->actingAs($otherOwner)->patch(publicationRoute('publish', $otherTeam, $summary))->assertNotFound();
    expect($summary->fresh()->status)->toBe('approved');
});

test('legacy drafts without a trustworthy source and audience snapshot cannot be published', function () {
    [$team, $owner, , $agreement] = publicationContext();
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => $agreement->id, 'type' => 'investor_progress',
        'status' => 'approved', 'final_text' => 'Legacy reviewed text',
        'generation_context' => null, 'source_snapshot' => null,
    ]);
    $this->actingAs($owner)->patch(publicationRoute('publish', $team, $summary))->assertSessionHasErrors();
    expect($summary->fresh()->status)->toBe('approved');
});

test('a changed source or agreement prevents publication of a previously reviewed draft', function (string $change) {
    [$team, $owner, , $agreement, $activity, $summary] = publicationContext();
    approvePublicationDraft($team, $owner, $summary);

    match ($change) {
        'source revoked' => $activity->update(['investor_safe_summary' => null]),
        'source edited' => $activity->update(['investor_safe_summary' => 'An edited summary.']),
        'source deleted' => $activity->delete(),
        'agreement suspended' => $agreement->update(['status' => InvestorAgreementStatus::Suspended]),
        'investor reassigned' => $agreement->update(['investor_id' => User::factory()->create()->id]),
        'farm reassigned' => $agreement->update(['farm_id' => Farm::factory()->create(['team_id' => $team->id])->id]),
        'cycle removed' => $agreement->update(['production_cycle_id' => null]),
    };

    $this->actingAs($owner)->patch(publicationRoute('publish', $team, $summary))->assertSessionHasErrors();
    expect($summary->fresh()->status)->toBe('approved');
    expect(AuditEvent::query()->where('subject_id', $summary->id)->where('action', 'ai_summary.published')->exists())->toBeFalse();
})->with(['source revoked', 'source edited', 'source deleted', 'agreement suspended', 'investor reassigned', 'farm reassigned', 'cycle removed']);

test('publication rejects other summary types and cannot be repeated', function () {
    [$team, $owner, , , , $summary] = publicationContext();
    approvePublicationDraft($team, $owner, $summary);
    $summary->update(['type' => 'weekly']);
    $this->actingAs($owner)->patch(publicationRoute('publish', $team, $summary))->assertSessionHasErrors();
    $summary->update(['type' => 'investor_progress']);

    $this->patch(publicationRoute('publish', $team, $summary))->assertRedirect()->assertSessionHasNoErrors();
    $this->patch(publicationRoute('publish', $team, $summary))->assertSessionHasErrors();
    expect(AuditEvent::query()->where('subject_id', $summary->id)->where('action', 'ai_summary.published')->count())->toBe(1);
});

test('publication fails closed when stored source evidence is malformed', function (string $corruption) {
    [$team, $owner, , , , $summary] = publicationContext();
    approvePublicationDraft($team, $owner, $summary);
    $snapshot = $summary->fresh()->source_snapshot;
    $invalid = match ($corruption) {
        'missing' => null,
        'empty' => [],
        'missing id' => [['summary' => 'Maize was planted.']],
        'string id' => [['id' => '1']],
        'duplicate id' => [$snapshot[0], $snapshot[0]],
    };
    $summary->update(['source_snapshot' => $invalid]);

    $this->actingAs($owner)->patch(publicationRoute('publish', $team, $summary))->assertSessionHasErrors('publication');
    expect($summary->fresh()->status)->toBe('approved');
})->with(['missing', 'empty', 'missing id', 'string id', 'duplicate id']);

test('only a published summary can be withdrawn', function () {
    [$team, $owner, , $agreement, , $summary] = publicationContext();
    $summary->update(['production_cycle_id' => $agreement->production_cycle_id]);
    expect($summary->fresh()->productionCycle->is($agreement->productionCycle))->toBeTrue();

    $this->actingAs($owner)->patch(publicationRoute('withdraw', $team, $summary))->assertSessionHasErrors('publication');
    expect($summary->fresh()->status)->toBe('draft');
    $this->assertDatabaseMissing('audit_events', ['action' => 'ai_summary.withdrawn', 'subject_id' => $summary->id]);
});
