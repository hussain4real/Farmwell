<?php

use App\Actions\Teams\ResolveInvestorMode;
use App\Enums\CropGrowthStage;
use App\Enums\EnterpriseSubtype;
use App\Enums\FarmType;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionUnitType;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\HealthEvent;
use App\Models\InventoryItem;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function revampTeamUser(TeamRole $role = TeamRole::Owner): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create(['name' => fake()->unique()->company().' Farms']);
    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$user, $team];
}

test('adaptive investor mode defaults to false for standalone farms and activates via signals or settings', function () {
    [$owner, $team] = revampTeamUser();
    $resolver = app(ResolveInvestorMode::class);

    expect($resolver->handle($team))->toBeFalse();

    $this->actingAs($owner)
        ->get(route('dashboard', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentTeamFeatures.investorMode', false)
            ->where('currentTeamFeatures.needsOnboarding', true)
        );

    // Invited investor activates auto-detection
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'pending-investor@example.com',
        'role' => TeamRole::Investor,
        'invited_by' => $owner->id,
    ]);
    expect($resolver->handle($team))->toBeTrue();
    $invitation->delete();

    // Investor member activates auto-detection
    $investor = User::factory()->create();
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    expect($resolver->handle($team))->toBeTrue();
    $team->members()->detach($investor);

    // Explicit team setting overrides detection
    $this->actingAs($owner)
        ->patch(route('teams.settings.update', [$team, 'workspace', 'investor_mode']), [
            'value' => ['enabled' => true],
            'reason' => 'Raising capital',
        ])
        ->assertRedirect(route('teams.edit', $team));

    expect($resolver->handle($team))->toBeTrue();

    $this->actingAs($owner)
        ->get(route('teams.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('investorMode', true));

    // Toggle back to standalone mode
    TeamSetting::query()
        ->where('team_id', $team->id)
        ->where('setting_group', 'workspace')
        ->where('setting_key', 'investor_mode')
        ->update(['value' => ['enabled' => false]]);

    expect($resolver->handle($team))->toBeFalse();

    // Creating an investor agreement re-enables investor mode
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);

    $this->actingAs($owner)
        ->post(route('investors.agreements.store', $team), [
            'investor_id' => $investor->id,
            'farm_id' => $farm->id,
            'title' => 'Seed Agreement',
            'amount_committed' => '500000',
            'investor_profit_share_percentage' => 40,
            'farm_profit_share_percentage' => 60,
        ])
        ->assertRedirect(route('investors.index', $team));

    expect($resolver->handle($team))->toBeTrue();
    expect(InvestorAgreement::query()->where('team_id', $team->id)->exists())->toBeTrue();

    // Approval request also triggers when setting absent
    TeamSetting::query()->where('team_id', $team->id)->where('setting_key', 'investor_mode')->delete();
    ApprovalRequest::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => InvestorAgreement::query()->where('team_id', $team->id)->firstOrFail()->id,
        'requested_by_id' => $owner->id,
    ]);
    expect($resolver->handle($team))->toBeTrue();
});

test('guided onboarding creates poultry, crop, and fish farms with units, cycles, tasks, inventory, and health schedules', function () {
    [$owner, $team] = revampTeamUser();

    $this->actingAs($owner)
        ->get(route('onboarding.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('onboarding/Index')
            ->has('templates', 6)
        );

    // Onboard Poultry Broiler farm
    $this->actingAs($owner)
        ->post(route('onboarding.store', $team), [
            'template_key' => 'poultry_broiler',
            'currency' => 'NGN',
            'investor_mode' => false,
            'farm_name' => 'Brothers Poultry',
            'farm_type' => FarmType::Poultry->value,
            'location' => 'Ibadan',
            'units' => [
                [
                    'name' => 'Broiler House 1',
                    'unit_type' => ProductionUnitType::House->value,
                    'size' => 100,
                    'size_unit' => 'sqm',
                    'capacity' => '500 birds',
                    'housing_system' => 'Deep litter',
                ],
            ],
            'commodity_name' => 'Broiler Chicken',
            'commodity_unit' => 'birds',
            'cycle_name' => 'Batch 1 Broilers',
            'enterprise_subtype' => EnterpriseSubtype::Broiler->value,
            'breed_or_variety' => 'Cobb 500',
            'planned_start_on' => now()->toDateString(),
            'planned_end_on' => now()->addDays(49)->toDateString(),
            'initial_stock_count' => 500,
            'initial_average_weight_g' => 42,
            'target_weight_g' => 2500,
            'cycle_status' => ProductionCycleStatus::Active->value,
            'create_starter_tasks' => true,
            'create_starter_inventory' => true,
            'create_health_schedule' => true,
        ])
        ->assertRedirect();

    $poultryFarm = Farm::query()->where('team_id', $team->id)->where('name', 'Brothers Poultry')->firstOrFail();

    expect($poultryFarm->productionUnits()->count())->toBe(1)
        ->and($poultryFarm->productionCycles()->firstOrFail()->current_stock_count)->toBe(500)
        ->and($poultryFarm->farmTasks()->count())->toBe(5)
        ->and(InventoryItem::query()->where('farm_id', $poultryFarm->id)->count())->toBe(3)
        ->and(HealthEvent::query()->where('farm_id', $poultryFarm->id)->count())->toBe(5)
        ->and(AuditEvent::query()->where('team_id', $team->id)->where('action', 'farm.onboarded')->exists())->toBeTrue();

    // Onboard Crop farm
    $this->actingAs($owner)
        ->post(route('onboarding.store', $team), [
            'template_key' => 'crop_arable',
            'farm_name' => 'Brothers Crop Farm',
            'farm_type' => FarmType::Crop->value,
            'units' => [
                [
                    'name' => 'Maize Field A',
                    'unit_type' => ProductionUnitType::Field->value,
                    'size' => 4,
                    'size_unit' => 'acres',
                    'water_source' => 'Rain-fed',
                ],
            ],
            'commodity_name' => 'Maize',
            'commodity_unit' => 'kg',
            'cycle_name' => 'Maize 2026',
            'enterprise_subtype' => EnterpriseSubtype::ArableCrop->value,
            'current_growth_stage' => CropGrowthStage::Planting->value,
            'planned_start_on' => now()->toDateString(),
            'create_starter_tasks' => true,
            'create_starter_inventory' => true,
            'create_health_schedule' => true,
        ])
        ->assertRedirect();

    $cropFarm = Farm::query()->where('team_id', $team->id)->where('name', 'Brothers Crop Farm')->firstOrFail();
    expect($cropFarm->productionCycles()->firstOrFail()->current_growth_stage)->toBe(CropGrowthStage::Planting);

    // Onboard Fish farm
    $this->actingAs($owner)
        ->post(route('onboarding.store', $team), [
            'template_key' => 'aquaculture_catfish',
            'farm_name' => 'Brothers Fish Farm',
            'farm_type' => FarmType::Aquaculture->value,
            'units' => [
                [
                    'name' => 'Pond 1',
                    'unit_type' => ProductionUnitType::Pond->value,
                    'size' => 30,
                    'size_unit' => 'sqm',
                    'water_source' => 'Borehole',
                ],
            ],
            'commodity_name' => 'African Catfish',
            'commodity_unit' => 'kg',
            'cycle_name' => 'Catfish Batch 1',
            'enterprise_subtype' => EnterpriseSubtype::Catfish->value,
            'initial_stock_count' => 1000,
            'planned_start_on' => now()->toDateString(),
            'cycle_status' => ProductionCycleStatus::Planned->value,
            'create_starter_tasks' => false,
            'create_starter_inventory' => false,
            'create_health_schedule' => false,
        ])
        ->assertRedirect();

    $fishFarm = Farm::query()->where('team_id', $team->id)->where('name', 'Brothers Fish Farm')->firstOrFail();
    expect($fishFarm->productionCycles()->firstOrFail()->actual_start_on)->toBeNull();
});

test('farm and cycle command centers render and support full edit lifecycle and redirects', function () {
    [$owner, $team] = revampTeamUser();
    $farm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Poultry]);
    $unit = ProductionUnit::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'unit_type' => ProductionUnitType::House]);
    $commodity = Commodity::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Poultry]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'farm_type' => FarmType::Poultry,
        'status' => ProductionCycleStatus::Planned,
        'actual_start_on' => null,
        'actual_end_on' => null,
    ]);
    $cycle->productionUnits()->attach($unit);
    $cycle->commodities()->attach($commodity, ['role' => 'primary']);

    // Show farm command center
    $this->actingAs($owner)
        ->get(route('farms.show', [$team, $farm]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('farms/Show')
            ->where('farm.id', $farm->id)
            ->has('cycles', 1)
        );

    // Edit farm
    $this->actingAs($owner)
        ->patch(route('farms.update', [$team, $farm]), [
            'name' => 'Updated Poultry HQ',
            'farm_type' => FarmType::Poultry->value,
            'location' => 'Lagos',
            'status' => 'active',
        ])
        ->assertRedirect(route('farms.show', [$team, $farm]));

    expect($farm->refresh()->name)->toBe('Updated Poultry HQ');

    // Create & Edit production unit with return_to=farm
    $this->actingAs($owner)
        ->post(route('farms.production-units.store', [$team, $farm]), [
            'name' => 'Brooder Room',
            'unit_type' => ProductionUnitType::House->value,
            'return_to' => 'farm',
        ])
        ->assertRedirect(route('farms.show', [$team, $farm]));

    $this->actingAs($owner)
        ->patch(route('farms.production-units.update', [$team, $farm, $unit]), [
            'name' => 'Renamed House',
            'unit_type' => ProductionUnitType::House->value,
            'status' => 'in_use',
            'housing_system' => 'Cage',
        ])
        ->assertRedirect(route('farms.show', [$team, $farm]));

    expect($unit->refresh()->name)->toBe('Renamed House');

    // Create cycle with return_to=farm and initial_stock_count
    $this->actingAs($owner)
        ->post(route('farms.production-cycles.store', [$team, $farm]), [
            'name' => 'Second Batch',
            'farm_type' => FarmType::Poultry->value,
            'planned_start_on' => now()->toDateString(),
            'production_unit_ids' => [$unit->id],
            'primary_commodity_id' => $commodity->id,
            'initial_stock_count' => 250,
            'return_to' => 'farm',
        ])
        ->assertRedirect(route('farms.show', [$team, $farm]));

    $secondCycle = ProductionCycle::query()->where('farm_id', $farm->id)->where('name', 'Second Batch')->firstOrFail();
    expect($secondCycle->current_stock_count)->toBe(250);

    // Show & Update cycle status planned -> active -> completed
    $this->actingAs($owner)
        ->get(route('farms.production-cycles.show', [$team, $farm, $cycle]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cycles/Show')
            ->where('cycle.id', $cycle->id)
        );

    $this->actingAs($owner)
        ->patch(route('farms.production-cycles.update', [$team, $farm, $cycle]), [
            'status' => ProductionCycleStatus::Active->value,
            'initial_stock_count' => 300,
            'production_unit_ids' => [$unit->id],
        ])
        ->assertRedirect(route('farms.production-cycles.show', [$team, $farm, $cycle]));

    expect($cycle->refresh()->actual_start_on)->not->toBeNull()
        ->and($cycle->current_stock_count)->toBe(300);

    $this->actingAs($owner)
        ->patch(route('farms.production-cycles.update', [$team, $farm, $cycle]), [
            'name' => 'Batch Alpha Updated',
        ])
        ->assertRedirect(route('farms.production-cycles.show', [$team, $farm, $cycle]));

    $this->actingAs($owner)
        ->patch(route('farms.production-cycles.update', [$team, $farm, $cycle]), [
            'status' => ProductionCycleStatus::Completed->value,
        ])
        ->assertRedirect(route('farms.production-cycles.show', [$team, $farm, $cycle]));

    expect($cycle->refresh()->actual_end_on)->not->toBeNull();

    // Custom onboarding without template_key
    $this->actingAs($owner)
        ->post(route('onboarding.store', $team), [
            'farm_name' => 'Custom Homestead',
            'farm_type' => FarmType::Mixed->value,
            'units' => [
                ['name' => 'Zone A', 'unit_type' => ProductionUnitType::Plot->value],
            ],
            'commodity_name' => 'Okra',
            'cycle_name' => 'Dry Season Run',
            'planned_start_on' => now()->toDateString(),
            'create_health_schedule' => false,
        ])
        ->assertRedirect();

    expect(Farm::query()->where('team_id', $team->id)->where('name', 'Custom Homestead')->exists())->toBeTrue();
});

test('onboarding and farm edits enforce role permissions and cross-team isolation', function () {
    [$owner, $team] = revampTeamUser();
    [$operator] = revampTeamUser(TeamRole::Operator);
    [$investor] = revampTeamUser(TeamRole::Investor);
    $team->members()->attach($operator, ['role' => TeamRole::Operator->value]);
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);

    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $unit = ProductionUnit::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);
    $cycle = ProductionCycle::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);

    [, $otherTeam] = revampTeamUser();
    $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);

    // Operator can view farm & cycle command centers, cannot onboard or update farm
    $this->actingAs($operator)->get(route('farms.show', [$team, $farm]))->assertOk();
    $this->actingAs($operator)->get(route('farms.production-cycles.show', [$team, $farm, $cycle]))->assertOk();
    $this->actingAs($operator)->get(route('onboarding.index', $team))->assertForbidden();
    $this->actingAs($operator)->post(route('onboarding.store', $team), [])->assertForbidden();
    $this->actingAs($operator)->patch(route('farms.update', [$team, $farm]), [])->assertForbidden();
    $this->actingAs($operator)->patch(route('farms.production-units.update', [$team, $farm, $unit]), [])->assertForbidden();
    $this->actingAs($operator)->patch(route('farms.production-cycles.update', [$team, $farm, $cycle]), [])->assertForbidden();

    // Investor is forbidden
    $this->actingAs($investor)->get(route('farms.show', [$team, $farm]))->assertForbidden();

    // Cross-team route binding returns 404
    $this->actingAs($owner)->get(route('farms.show', [$team, $otherFarm]))->assertNotFound();
});
