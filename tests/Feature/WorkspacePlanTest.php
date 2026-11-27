<?php

use App\Actions\Teams\ResolveInvestorMode;
use App\Actions\Teams\ResolveWorkspacePlan;
use App\Enums\EnterpriseSubtype;
use App\Enums\FarmType;
use App\Enums\PlatformRole;
use App\Enums\ProductionUnitType;
use App\Enums\TeamRole;
use App\Enums\WorkspacePlan;
use App\Models\AuditEvent;
use App\Models\Farm;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('new self-registered users start on starter while legacy teams without a plan setting resolve to investor', function () {
    $this->post(route('register.store'), [
        'name' => 'Brother Poultry',
        'email' => 'poultry@example.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('dashboard'));

    $newUser = User::where('email', 'poultry@example.test')->firstOrFail();
    $newTeam = $newUser->personalTeam();
    expect($newTeam)->not->toBeNull();

    $plans = app(ResolveWorkspacePlan::class);
    expect($plans->handle($newTeam))->toBe(WorkspacePlan::Starter);
    expect($newTeam->load('settings')->relationLoaded('settings'))->toBeTrue();
    expect($plans->handle($newTeam))->toBe(WorkspacePlan::Starter);

    $legacyTeam = Team::factory()->create();
    expect($plans->handle($legacyTeam))->toBe(WorkspacePlan::Investor);

    TeamSetting::query()->create([
        'team_id' => $legacyTeam->id,
        'setting_group' => ResolveWorkspacePlan::SettingGroup,
        'setting_key' => ResolveWorkspacePlan::SettingKey,
        'value' => ['plan' => 'unknown-tier'],
    ]);
    expect($plans->handle($legacyTeam))->toBe(WorkspacePlan::Investor);

    expect(WorkspacePlan::options())->toHaveCount(3)
        ->and(WorkspacePlan::Starter->summary()['maxFarms'])->toBe(1)
        ->and(WorkspacePlan::Pro->summary()['maxMembers'])->toBeNull()
        ->and(WorkspacePlan::Investor->summary()['allowsInvestorMode'])->toBeTrue();
});

test('starter workspace enforces 1 farm, 2 email seats, and blocks sms, investor, and ai features', function () {
    config([
        'services.phone_login.enabled' => true,
        'services.phone_login.provider' => 'infobip',
        'services.phone_login.pilot_host' => 'app.farmwell.app',
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->starter()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $this->actingAs($owner);

    // 1. Starter can onboard/create 1 farm
    $this->post(route('onboarding.store', $team), [
        'template_key' => 'poultry_broiler',
        'farm_name' => 'Broiler House Alpha',
        'farm_type' => FarmType::Poultry->value,
        'investor_mode' => false,
        'units' => [
            ['name' => 'Pen 1', 'unit_type' => ProductionUnitType::House->value],
        ],
        'commodity_name' => 'Broiler Chicken',
        'cycle_name' => 'Batch 1',
        'enterprise_subtype' => EnterpriseSubtype::Broiler->value,
        'planned_start_on' => now()->toDateString(),
    ])->assertRedirect();

    $firstFarm = Farm::where('team_id', $team->id)->sole();

    // 2. Starter is blocked from creating a 2nd farm via farms.store or onboarding.store
    $this->post(route('farms.store', $team), [
        'name' => 'Second Farm Blocked',
        'farm_type' => FarmType::Poultry->value,
    ])->assertSessionHasErrors('name');

    $this->post(route('onboarding.store', $team), [
        'farm_name' => 'Second Onboarded Farm',
        'farm_type' => FarmType::Poultry->value,
        'investor_mode' => true,
        'units' => [
            ['name' => 'Pen 2', 'unit_type' => ProductionUnitType::House->value],
        ],
        'commodity_name' => 'Layers',
        'cycle_name' => 'Batch 2',
        'planned_start_on' => now()->toDateString(),
    ])->assertSessionHasErrors(['farm_name', 'investor_mode']);

    // 3. Starter blocks SMS phone invite and Investor role invite
    $this->post(route('teams.invitations.store', $team), [
        'phone' => '+2348031234567',
        'role' => TeamRole::Operator->value,
    ])->assertSessionHasErrors(['phone' => 'SMS phone invitations require the Pro Farm or Investor plan.']);

    $this->post(route('teams.invitations.store', $team), [
        'email' => 'investor@example.test',
        'role' => TeamRole::Investor->value,
    ])->assertSessionHasErrors('role');

    // 4. Starter allows inviting 1 email worker (2nd total seat) and blocks 3rd seat
    $this->post(route('teams.invitations.store', $team), [
        'email' => 'worker1@example.test',
        'role' => TeamRole::Operator->value,
    ])->assertRedirect(route('teams.edit', $team));

    $this->post(route('teams.invitations.store', $team), [
        'email' => 'worker2@example.test',
        'role' => TeamRole::Operator->value,
    ])->assertSessionHasErrors('email');

    // 5. Teams edit exposes plan summary, capacity, hides phone login & investor role
    $this->get(route('teams.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan.key', 'starter')
            ->where('canAddMember', false)
            ->where('usedSeatCount', 2)
            ->where('phoneLoginEnabled', false)
            ->where('investorMode', false)
            ->where('availableRoles', fn ($roles): bool => collect($roles)->doesntContain('value', TeamRole::Investor->value)));

    // 6. Blocks self-upgrading plan setting or enabling investor mode
    $this->patch(route('teams.settings.update', [
        'team' => $team,
        'settingGroup' => ResolveWorkspacePlan::SettingGroup,
        'settingKey' => ResolveWorkspacePlan::SettingKey,
    ]), [
        'value' => ['plan' => WorkspacePlan::Investor->value],
    ])->assertForbidden();

    $this->patch(route('teams.settings.update', [
        'team' => $team,
        'settingGroup' => ResolveInvestorMode::SettingGroup,
        'settingKey' => ResolveInvestorMode::SettingKey,
    ]), [
        'value' => ['enabled' => true],
        'reason' => 'Try to enable investor mode on starter',
    ])->assertSessionHasErrors('value');

    expect(app(ResolveInvestorMode::class)->handle($team))->toBeFalse();

    // 7. Blocks AI summaries creation and investor agreement creation
    $this->get(route('ai-summaries.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('aiSummariesEnabled', false));

    $this->post(route('ai-summaries.store', $team), [
        'type' => 'weekly',
        'farm_id' => $firstFarm->id,
    ])->assertForbidden();

    $investor = User::factory()->create();
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);

    $this->post(route('investors.agreements.store', $team), [
        'investor_id' => $investor->id,
        'farm_id' => $firstFarm->id,
        'title' => 'Blocked Agreement',
        'amount_committed' => '100000.00',
        'investor_profit_share_percentage' => 40,
        'farm_profit_share_percentage' => 60,
    ])->assertForbidden();

    expect(InvestorAgreement::where('team_id', $team->id)->count())->toBe(0);
});

test('pro workspace unlocks multiple farms, unlimited email/sms invites, while keeping investor and ai gated', function () {
    config([
        'services.phone_login.enabled' => true,
        'services.phone_login.provider' => 'infobip',
        'services.phone_login.pilot_host' => 'app.farmwell.app',
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->pro()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $this->actingAs($owner);

    $this->post(route('farms.store', $team), [
        'name' => 'Poultry Farm One',
        'farm_type' => FarmType::Poultry->value,
    ])->assertRedirect(route('farms.index', $team));

    $this->post(route('farms.store', $team), [
        'name' => 'Poultry Farm Two',
        'farm_type' => FarmType::Poultry->value,
    ])->assertRedirect(route('farms.index', $team));

    $this->post(route('teams.invitations.store', $team), [
        'phone' => '+2348031234568',
        'role' => TeamRole::Operator->value,
    ])->assertRedirect(route('teams.edit', $team));

    $this->post(route('teams.invitations.store', $team), [
        'email' => 'manager@example.test',
        'role' => TeamRole::Admin->value,
    ])->assertRedirect(route('teams.edit', $team));

    $this->get(route('teams.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan.key', 'pro')
            ->where('canAddMember', true)
            ->where('phoneLoginEnabled', true));

    $farm = Farm::where('team_id', $team->id)->firstOrFail();

    $this->post(route('ai-summaries.store', $team), [
        'type' => 'weekly',
        'farm_id' => $farm->id,
    ])->assertForbidden();
});

test('super admin can update workspace plans from platform admin with audit trail and unlock gated features', function () {
    config([
        'services.phone_login.enabled' => true,
        'services.phone_login.provider' => 'infobip',
        'services.phone_login.pilot_host' => 'app.farmwell.app',
    ]);

    $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
    $owner = User::factory()->create();
    $team = Team::factory()->starter()->create(['name' => 'Poultry Pilot Farm']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $farm = Farm::factory()->for($team)->create(['name' => 'Broiler Unit']);

    $this->actingAs($admin);

    $this->get(route('platform.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('plans', 3)
            ->where('farms.data.0.team.plan', 'starter'));

    $this->patch(route('platform.workspaces.plan.update', $team), [
        'plan' => 'invalid-plan',
        'reason' => '',
    ])->assertSessionHasErrors(['plan', 'reason']);

    $this->patch(route('platform.workspaces.plan.update', $team), [
        'plan' => WorkspacePlan::Investor->value,
        'reason' => 'Upgraded workspace after manual transfer',
    ])->assertRedirect();

    expect(app(ResolveWorkspacePlan::class)->handle($team))->toBe(WorkspacePlan::Investor);

    $audit = AuditEvent::where('action', 'platform.workspace_plan_updated')->sole();
    expect($audit->team_id)->toBe($team->id)
        ->and($audit->actor_id)->toBe($admin->id)
        ->and($audit->old_values['plan'])->toBe(WorkspacePlan::Starter->value)
        ->and($audit->new_values['plan'])->toBe(WorkspacePlan::Investor->value)
        ->and($audit->reason)->toBe('Upgraded workspace after manual transfer');

    $this->actingAs($owner);
    $this->post(route('teams.invitations.store', $team), [
        'phone' => '+2348039991111',
        'role' => TeamRole::Investor->value,
    ])->assertRedirect(route('teams.edit', $team));
});
