<?php

use App\Enums\ApprovalRequestStatus;
use App\Enums\InvestorAgreementStatus;
use App\Enums\PlatformRole;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('the portfolio shows only accessible agreements, shared activity and pending decisions across farms', function () {
    $investor = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
    $otherInvestor = User::factory()->create();
    $pepperTeam = Team::factory()->create(['name' => 'Pepper workspace']);
    $fishTeam = Team::factory()->create(['name' => 'Fish workspace']);
    $privateTeam = Team::factory()->create(['name' => 'Unassigned workspace']);
    $pepperTeam->members()->attach([$investor->id => ['role' => TeamRole::Investor->value], $otherInvestor->id => ['role' => TeamRole::Investor->value]]);
    $fishTeam->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $privateTeam->members()->attach($investor, ['role' => TeamRole::Operator->value]);
    $investor->switchTeam($pepperTeam);
    $otherInvestor->switchTeam($pepperTeam);

    $pepper = Farm::factory()->create(['team_id' => $pepperTeam->id, 'name' => 'Pepper Farm']);
    $fish = Farm::factory()->create(['team_id' => $fishTeam->id, 'name' => 'Fish Farm']);
    $cycle = ProductionCycle::factory()->create(['farm_id' => $pepper->id, 'team_id' => $pepperTeam->id]);
    $pepperAgreement = InvestorAgreement::factory()->create([
        'team_id' => $pepperTeam->id, 'farm_id' => $pepper->id, 'investor_id' => $investor->id,
        'production_cycle_id' => $cycle->id, 'title' => 'Pepper season', 'amount_funded_minor' => 0, 'internal_notes' => 'Restricted agreement note',
    ]);
    $fishAgreement = InvestorAgreement::factory()->create([
        'team_id' => $fishTeam->id, 'farm_id' => $fish->id, 'investor_id' => $investor->id, 'title' => 'First fish batch',
        'created_at' => now()->subDay(),
    ]);
    $completedAgreement = InvestorAgreement::factory()->create([
        'team_id' => $fishTeam->id, 'farm_id' => $fish->id, 'investor_id' => $investor->id,
        'title' => 'Earlier fish batch', 'status' => InvestorAgreementStatus::Completed,
    ]);
    $otherAgreement = InvestorAgreement::factory()->create([
        'team_id' => $pepperTeam->id, 'investor_id' => $otherInvestor->id, 'title' => 'Unassigned agreement',
    ]);
    InvestorAgreement::factory()->create([
        'team_id' => $fishTeam->id, 'farm_id' => $fish->id, 'investor_id' => $investor->id,
        'status' => InvestorAgreementStatus::Suspended, 'title' => 'Suspended agreement',
    ]);
    InvestorAgreement::factory()->create(['team_id' => $privateTeam->id, 'investor_id' => $investor->id, 'title' => 'Restricted role agreement']);
    InvestorAgreement::factory()->create(['investor_id' => $investor->id, 'title' => 'Removed membership agreement']);

    $activity = FarmActivity::factory()->create([
        'team_id' => $pepperTeam->id, 'farm_id' => $pepper->id, 'activity_date' => '2026-11-12',
        'production_cycle_id' => $cycle->id, 'activity_type' => 'Nursery watering', 'investor_safe_summary' => 'Shared field notes',
    ]);
    FarmActivity::factory()->create([
        'team_id' => $pepperTeam->id, 'farm_id' => $pepper->id, 'activity_type' => 'Private field entry',
        'production_cycle_id' => $cycle->id, 'activity_date' => '2026-11-14', 'investor_safe_summary' => null,
    ]);
    FarmActivity::factory()->create([
        'team_id' => $pepperTeam->id, 'farm_id' => $pepper->id, 'activity_type' => 'Different cycle update',
        'production_cycle_id' => null, 'activity_date' => '2026-11-15', 'investor_safe_summary' => 'Shared on another cycle',
    ]);

    $pending = ApprovalRequest::factory()->create([
        'team_id' => $fishTeam->id, 'investor_agreement_id' => $fishAgreement->id, 'farm_id' => $fish->id,
        'requester_comment' => 'Detail available on assigned portal',
    ]);
    ApprovalRequest::factory()->create(['team_id' => $pepperTeam->id, 'investor_agreement_id' => $otherAgreement->id]);
    ApprovalRequest::factory()->create([
        'team_id' => $pepperTeam->id, 'investor_agreement_id' => $pepperAgreement->id, 'status' => ApprovalRequestStatus::Approved,
    ]);
    ApprovalRequest::factory()->create(['team_id' => $pepperTeam->id, 'investor_agreement_id' => $fishAgreement->id]);

    $portfolioResponse = $this->actingAs($investor)->get(route('my-investments.index'));
    $portfolioResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('investor-portal/Portfolio')
            ->has('farms', 2)
            ->where('farms.0.name', 'Fish Farm')->has('farms.0.agreements', 2)
            ->where('farms.0.agreements.0.id', $completedAgreement->id)
            ->where('farms.0.agreements.1.id', $fishAgreement->id)->where('farms.0.agreements.1.latestActivity', null)
            ->where('farms.1.name', 'Pepper Farm')->has('farms.1.agreements', 1)
            ->where('farms.1.agreements.0.id', $pepperAgreement->id)->where('farms.1.agreements.0.funded', '0.00')
            ->where('farms.1.agreements.0.latestActivity.title', $activity->activity_type)
            ->where('farms.1.agreements.0.latestActivity.date', '2026-11-12')
            ->missing('farms.1.agreements.0.internalNotes')
            ->has('attention', 1)->where('attention.0.requestId', $pending->id)
            ->where('attention.0.teamSlug', $fishTeam->slug)->where('attention.0.agreementId', $fishAgreement->id)
            ->missing('attention.0.requesterComment'));
    expect($investor->fresh()->current_team_id)->toBe($pepperTeam->id);

    $decisionUrl = route('investor-portal.index', ['current_team' => $fishTeam, 'agreement' => $fishAgreement->id, 'request' => $pending->id]);
    $decisionResponse = $this->get($decisionUrl);
    $decisionResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('investor-portal/Index')->has('agreements', 2)->where('agreements.1.id', $fishAgreement->id));
    $restrictedResponse = $this->actingAs($otherInvestor)->get(route('my-investments.index', ['agreement' => $fishAgreement->id]));
    $restrictedResponse
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('farms', 1)
        ->has('farms.0.agreements', 1)->where('farms.0.agreements.0.id', $otherAgreement->id)
        ->has('attention', 1)->where('attention.0.agreementId', $otherAgreement->id));
    $this->get($decisionUrl)->assertForbidden();
    $this->get(route('investor-portal.index', ['current_team' => $pepperTeam, 'agreement' => $pepperAgreement->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('agreements', 1)->where('agreements.0.id', $otherAgreement->id));
});

test('the portfolio requires a verified account and can show an empty portfolio', function () {
    $url = route('my-investments.index');
    $this->get($url)->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())->get($url)->assertRedirect(route('verification.notice'));

    $this->actingAs(User::factory()->create())->get($url)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('investor-portal/Portfolio')->where('farms', [])->where('attention', []));
});
