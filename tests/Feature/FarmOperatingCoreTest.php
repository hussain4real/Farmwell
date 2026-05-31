<?php

use App\Enums\CommodityRole;
use App\Enums\FarmType;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionPlanChangeType;
use App\Enums\ProductionUnitType;
use App\Enums\TeamRole;
use App\Models\AuditEvent;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\ProductionPlanChange;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function farmwellOperatingUser(TeamRole $role = TeamRole::Owner): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create(['name' => fake()->unique()->company().' Operations']);

    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$user, $team];
}

test('owners can create farm operating records with mixed commodities', function () {
    [$owner, $team] = farmwellOperatingUser();

    $this
        ->actingAs($owner)
        ->get(route('farms.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('farms/Index')
            ->where('permissions.canManageFarmOperations', true)
            ->where('stats.farms', 0)
            ->where('stats.productionUnits', 0)
            ->where('stats.productionCycles', 0)
            ->where('stats.commodities', 0)
        );

    $this
        ->actingAs($owner)
        ->post(route('commodities.store', $team), [
            'name' => 'Maize',
            'farm_type' => FarmType::Crop->value,
            'measurement_unit' => 'kg',
        ])
        ->assertRedirect(route('farms.index', $team));

    $this
        ->actingAs($owner)
        ->post(route('commodities.store', $team), [
            'name' => 'Pepper',
            'farm_type' => FarmType::Crop->value,
            'measurement_unit' => 'crates',
        ])
        ->assertRedirect(route('farms.index', $team));

    $this
        ->actingAs($owner)
        ->post(route('farms.store', $team), [
            'name' => 'North Farm',
            'farm_type' => FarmType::Mixed->value,
            'location' => 'Kaduna',
            'contact_name' => 'Farm Manager',
            'contact_phone' => '+2348000000000',
        ])
        ->assertRedirect(route('farms.index', $team));

    $farm = Farm::query()->where('team_id', $team->id)->where('name', 'North Farm')->firstOrFail();

    $this
        ->actingAs($owner)
        ->post(route('farms.production-units.store', [$team, $farm]), [
            'name' => 'Block A',
            'unit_type' => ProductionUnitType::Field->value,
            'size' => 2.5,
            'size_unit' => 'hectares',
        ])
        ->assertRedirect(route('farms.index', $team));

    $unit = ProductionUnit::query()->where('farm_id', $farm->id)->firstOrFail();
    $maize = Commodity::query()->where('team_id', $team->id)->where('name', 'Maize')->firstOrFail();
    $pepper = Commodity::query()->where('team_id', $team->id)->where('name', 'Pepper')->firstOrFail();

    $this
        ->actingAs($owner)
        ->post(route('farms.production-cycles.store', [$team, $farm]), [
            'name' => '2026 Maize and Pepper',
            'season' => '2026 main season',
            'farm_type' => FarmType::Mixed->value,
            'production_method' => 'Rain-fed',
            'planned_start_on' => '2026-06-01',
            'planned_end_on' => '2026-10-31',
            'expected_output_quantity' => 25,
            'expected_output_unit' => 'tons',
            'status' => ProductionCycleStatus::Planned->value,
            'production_unit_ids' => [$unit->id],
            'primary_commodity_id' => $maize->id,
            'secondary_commodity_id' => $pepper->id,
        ])
        ->assertRedirect(route('farms.index', $team));

    $cycle = ProductionCycle::query()->where('farm_id', $farm->id)->where('name', '2026 Maize and Pepper')->firstOrFail();

    expect($cycle->team_id)->toBe($team->id)
        ->and($cycle->productionUnits()->whereKey($unit->id)->exists())->toBeTrue()
        ->and($cycle->commodities()->whereKey($maize->id)->firstOrFail()->pivot->role)->toBe(CommodityRole::Primary->value)
        ->and($cycle->commodities()->whereKey($pepper->id)->firstOrFail()->pivot->role)->toBe(CommodityRole::Intercrop->value);

    $this
        ->actingAs($owner)
        ->post(route('farms.production-cycles.store', [$team, $farm]), [
            'name' => 'Structured Commodity Cycle',
            'season' => '2026 dry season',
            'farm_type' => FarmType::Crop->value,
            'planned_start_on' => '2026-11-01',
            'production_unit_ids' => [$unit->id],
            'commodities' => [
                [
                    'id' => $maize->id,
                    'role' => CommodityRole::Primary->value,
                    'expected_output_quantity' => 10,
                    'expected_output_unit' => 'tons',
                    'notes' => 'Dry season maize',
                ],
            ],
        ])
        ->assertRedirect(route('farms.index', $team));

    $this
        ->actingAs($owner)
        ->get(route('farms.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('farms/Index')
            ->where('stats.farms', 1)
            ->where('stats.productionUnits', 1)
            ->where('stats.productionCycles', 2)
            ->where('stats.commodities', 2)
            ->where('farms.0.name', 'North Farm')
            ->where('farms.0.productionUnits.0.name', 'Block A')
            ->where('farms.0.productionCycles.0.commodities.0.role', CommodityRole::Primary->value)
        );

    expect(AuditEvent::query()->where('team_id', $team->id)->pluck('action')->all())
        ->toContain('commodity.created', 'farm.created', 'production_unit.created', 'production_cycle.created');
});

test('plan changes capture reason impact and audit trail', function () {
    [$owner, $team] = farmwellOperatingUser();
    $farm = Farm::factory()->create([
        'team_id' => $team->id,
        'name' => 'Rice Farm',
        'farm_type' => FarmType::Crop,
    ]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Rice Cycle',
        'plan_version' => 1,
    ]);

    $this
        ->actingAs($owner)
        ->post(route('farms.production-cycles.plan-changes.store', [$team, $farm, $cycle]), [
            'change_type' => ProductionPlanChangeType::Schedule->value,
            'reason' => 'Rain delayed land preparation',
            'impact' => 'Planting moves by ten days',
            'investor_safe_summary' => 'Schedule adjusted after rain delay.',
            'old_values' => [
                'planned_start_on' => '2026-06-01',
            ],
            'new_values' => [
                'planned_start_on' => '2026-06-10',
            ],
        ])
        ->assertRedirect(route('farms.index', $team));

    $planChange = ProductionPlanChange::query()->firstOrFail();

    expect($cycle->refresh()->plan_version)->toBe(2)
        ->and($planChange->team_id)->toBe($team->id)
        ->and($planChange->actor_id)->toBe($owner->id)
        ->and($planChange->reason)->toBe('Rain delayed land preparation')
        ->and($planChange->impact)->toBe('Planting moves by ten days')
        ->and($planChange->old_values)->toBe([
            'plan_version' => 1,
            'planned_start_on' => '2026-06-01',
        ])
        ->and($planChange->new_values)->toBe([
            'plan_version' => 2,
            'planned_start_on' => '2026-06-10',
        ]);

    $auditEvent = AuditEvent::query()->where('action', 'production_plan_change.recorded')->firstOrFail();

    expect($auditEvent->team_id)->toBe($team->id)
        ->and($auditEvent->actor_id)->toBe($owner->id)
        ->and($auditEvent->reason)->toBe('Rain delayed land preparation')
        ->and($auditEvent->new_values['impact'])->toBe('Planting moves by ten days');

    $this
        ->actingAs($owner)
        ->get(route('farms.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('latestPlanChanges.0.reason', 'Rain delayed land preparation')
            ->where('latestPlanChanges.0.impact', 'Planting moves by ten days')
        );
});

test('status fields reject explicit null so database defaults can apply when omitted', function () {
    [$owner, $team] = farmwellOperatingUser();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $unit = ProductionUnit::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $commodity = Commodity::factory()->create(['team_id' => $team->id]);

    $this
        ->actingAs($owner)
        ->post(route('farms.store', $team), [
            'name' => 'Null Status Farm',
            'farm_type' => FarmType::Crop->value,
            'status' => null,
        ])
        ->assertSessionHasErrors('status');

    $this
        ->actingAs($owner)
        ->post(route('farms.production-units.store', [$team, $farm]), [
            'name' => 'Null Status Unit',
            'unit_type' => ProductionUnitType::Field->value,
            'status' => null,
        ])
        ->assertSessionHasErrors('status');

    $this
        ->actingAs($owner)
        ->post(route('farms.production-cycles.store', [$team, $farm]), [
            'name' => 'Null Status Cycle',
            'farm_type' => FarmType::Crop->value,
            'planned_start_on' => '2026-06-01',
            'status' => null,
            'production_unit_ids' => [$unit->id],
            'primary_commodity_id' => $commodity->id,
        ])
        ->assertSessionHasErrors('status');

    expect(Farm::query()->where('name', 'Null Status Farm')->exists())->toBeFalse()
        ->and(ProductionUnit::query()->where('name', 'Null Status Unit')->exists())->toBeFalse()
        ->and(ProductionCycle::query()->where('name', 'Null Status Cycle')->exists())->toBeFalse();
});

test('members can view but cannot manage farm operating records', function () {
    [$member, $team] = farmwellOperatingUser(TeamRole::Member);

    $this
        ->actingAs($member)
        ->get(route('farms.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canViewFarmOperations', true)
            ->where('permissions.canManageFarmOperations', false)
        );

    $this
        ->actingAs($member)
        ->post(route('farms.store', $team), [
            'name' => 'Member Farm',
            'farm_type' => FarmType::Crop->value,
        ])
        ->assertForbidden();

    expect(Farm::query()->where('team_id', $team->id)->exists())->toBeFalse();
});

test('farm operating routes are scoped to the current team', function () {
    [$owner, $team] = farmwellOperatingUser();
    $unrelatedUser = User::factory()->create();
    $otherTeam = Team::factory()->create(['name' => 'Other Farm Org']);
    $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);

    $this
        ->actingAs($unrelatedUser)
        ->get(route('farms.index', $team))
        ->assertForbidden();

    $this
        ->actingAs($owner)
        ->post(route('farms.production-units.store', [$team, $otherFarm]), [
            'name' => 'Wrong Team Unit',
            'unit_type' => ProductionUnitType::Field->value,
        ])
        ->assertNotFound();

    expect(ProductionUnit::query()->where('name', 'Wrong Team Unit')->exists())->toBeFalse();
});
