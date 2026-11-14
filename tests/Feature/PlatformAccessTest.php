<?php

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\PlatformRole;
use App\Models\AuditEvent;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use App\Support\PlatformAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
    $this->originalTeam = $this->admin->currentTeam;
    $this->team = Team::factory()->create();
    $this->farm = Farm::factory()->for($this->team)->create();
    $this->actingAs($this->admin);
});

function beginPlatformAccess(TestCase $test, ?Team $team = null): void
{
    $team ??= $test->team;
    $test->post(route('platform.access.store'), ['team_id' => $team->id, 'reason' => 'Investigate pilot records'])
        ->assertRedirect(route('dashboard', $team));
}

test('admin sessions expose only selected workspace records without altering memberships or current team', function () {
    $activity = FarmActivity::factory()->create(['team_id' => $this->team->id, 'farm_id' => $this->farm->id]);
    FarmActivity::factory()->create();
    $expense = Expense::factory()->create(['team_id' => $this->team->id, 'farm_id' => $this->farm->id]);
    Expense::factory()->create();
    $agreement = InvestorAgreement::factory()->create(['team_id' => $this->team->id, 'farm_id' => $this->farm->id]);
    InvestorAgreement::factory()->create();
    beginPlatformAccess($this);
    $this->get(route('field-diary.index', $this->team))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('latestActivities', 1)->where('latestActivities.0.id', $activity->id)
        ->where('currentTeam.id', $this->team->id)->where('platformAccess.team_id', $this->team->id)
        ->where('permissions.canManageFieldWork', false)->where('currentTeamPermissions.canManageFinance', false));
    $this->get(route('finance.expenses.index', $this->team))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('expenses', 1)->where('expenses.0.id', $expense->id)->where('permissions.canManageFinance', false));
    $this->get(route('investors.index', $this->team))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('agreements', 1)->where('agreements.0.id', $agreement->id)->where('permissions.canManageInvestorAgreements', false));
    expect($this->admin->fresh()->current_team_id)->toBe($this->originalTeam->id)
        ->and($this->admin->teams()->count())->toBe(1)
        ->and($this->admin->belongsToTeam($this->team))->toBeFalse();
    $events = AuditEvent::where('action', 'platform.workspace_viewed')->get();
    expect($events)->toHaveCount(3);
    foreach ($events as $event) {
        expect($event->actor_id)->toBe($this->admin->id)->and($event->team_id)->toBe($this->team->id)
            ->and($event->reason)->toBe('Investigate pilot records')
            ->and($event->metadata['access_id'])->toBe(session(PlatformAccess::SessionKey)['id']);
    }
});

test('every permitted read page renders and finance reads do not create categories', function (string $route) {
    beginPlatformAccess($this);
    $this->get(route($route, $this->team))->assertOk();
    expect(ExpenseCategory::count())->toBe(0);
})->with(['dashboard', 'farms.index', 'farm-tasks.index', 'whatsapp-intakes.index', 'harvests.index', 'finance.index', 'finance.budgets.index', 'finance.funding-phases.index', 'finance.external-transfers.index', 'investors.approvals.index']);

test('admin mode denies writes and other workspaces even when the admin owns them', function () {
    beginPlatformAccess($this, $this->originalTeam);
    $this->post(route('farm-activities.store', $this->originalTeam), [])->assertForbidden();
    $this->get(route('teams.edit', $this->originalTeam))->assertForbidden();
    $this->get(route('investor-portal.index', $this->originalTeam))->assertForbidden();
    $this->get(route('dashboard', $this->team))->assertForbidden();
    expect(FarmActivity::count())->toBe(0);
});

test('private evidence downloads are audited and cross-workspace media remain denied', function () {
    Storage::fake('farmwell_private');
    $activity = FarmActivity::factory()->create(['team_id' => $this->team->id, 'farm_id' => $this->farm->id]);
    $media = $activity->addMedia(UploadedFile::fake()->image('selected.jpg'))->toMediaCollection(FarmActivity::EvidenceCollection, 'farmwell_private');
    $other = FarmActivity::factory()->create();
    $otherMedia = $other->addMedia(UploadedFile::fake()->image('other.jpg'))->toMediaCollection(FarmActivity::EvidenceCollection, 'farmwell_private');
    beginPlatformAccess($this);
    foreach (['farm-evidence.show', 'finance.evidence.show', 'investor-evidence.show'] as $route) {
        $this->get(route($route, ['current_team' => $this->team->slug, 'media' => $media->id]))->assertDownload('selected.jpg');
        $this->get(route($route, ['current_team' => $this->team->slug, 'media' => $otherMedia->id]))->assertNotFound();
    }
    expect(AuditEvent::where('action', 'platform.workspace_viewed')->first()->metadata['media_id'])->toBe($media->id);
});

test('exit removes access and preserves the original workspace context', function () {
    beginPlatformAccess($this);
    $this->delete(route('platform.access.destroy'))->assertRedirectToRoute('platform.index')->assertSessionMissing(PlatformAccess::SessionKey);
    $this->get(route('dashboard', $this->team))->assertForbidden();
    $this->get(route('dashboard', $this->originalTeam))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('currentTeam.id', $this->originalTeam->id)->where('platformAccess', null));
    expect(AuditEvent::where('action', 'platform.workspace_exited')->count())->toBe(1);
    $this->delete(route('platform.access.destroy'))->assertRedirectToRoute('platform.index');
});

test('switching admin sessions requires another audited explicit selection', function () {
    beginPlatformAccess($this);
    $oldId = session(PlatformAccess::SessionKey)['id'];
    beginPlatformAccess($this, $this->originalTeam);
    $this->get(route('dashboard', $this->team))->assertForbidden();
    $this->get(route('dashboard', $this->originalTeam))->assertOk();
    expect(AuditEvent::where('action', 'platform.workspace_entered')->latest('id')->first()->metadata['replaces_access_id'])->toBe($oldId);
});

test('expired revoked or different-account sessions cannot authorize record access', function (string $condition) {
    beginPlatformAccess($this);
    match ($condition) {
        'expired' => $this->travel(31)->minutes(),
        'revoked' => $this->admin->forceFill(['platform_role' => null])->save(),
        'different-user' => $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::SuperAdmin])),
    };
    $this->get(route('dashboard', $this->team))->assertForbidden()->assertSessionMissing(PlatformAccess::SessionKey);
})->with(['expired', 'revoked', 'different-user']);

test('ordinary users and invalid selections cannot start administrative sessions', function () {
    $this->post(route('platform.access.store'), ['team_id' => $this->team->id])->assertSessionHasErrors('reason');
    $this->post(route('platform.access.store'), ['team_id' => 999999, 'reason' => 'Test'])->assertSessionHasErrors('team_id');
    $this->actingAs(User::factory()->create())->post(route('platform.access.store'), ['team_id' => $this->team->id, 'reason' => 'Test'])->assertForbidden();
    expect(AuditEvent::count())->toBe(0);
});

test('an audit outage denies record access and session entry', function () {
    beginPlatformAccess($this);
    $this->mock(RecordAuditEvent::class)->shouldReceive('handle')->twice()->andThrow(new RuntimeException('Audit unavailable'));
    $this->withoutExceptionHandling();
    expect(fn () => $this->get(route('field-diary.index', $this->team)))->toThrow(RuntimeException::class, 'Audit unavailable');
    $oldId = session(PlatformAccess::SessionKey)['id'];
    expect(fn () => $this->post(route('platform.access.store'), ['team_id' => $this->originalTeam->id, 'reason' => 'Test']))->toThrow(RuntimeException::class, 'Audit unavailable');
    expect(session(PlatformAccess::SessionKey)['id'])->toBe($oldId);
});
