<?php

use App\Enums\HealthEventStatus;
use App\Enums\HealthEventType;
use App\Enums\InventoryCategory;
use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\HealthEvent;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function healthContext(): array
{
    $owner = User::factory()->create();
    $operator = User::factory()->create();
    $investor = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($operator, ['role' => TeamRole::Operator->value]);
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $owner->switchTeam($team);

    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'planned_start_on' => now()->toDateString(),
    ]);

    return [$owner, $operator, $investor, $team, $farm, $cycle];
}

test('health events support scheduling, presets, completion with stock deduction, and withdrawal warnings', function () {
    [$owner, $operator, , $team, $farm, $cycle] = healthContext();

    $vaccine = InventoryItem::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Lasota Vial',
        'category' => InventoryCategory::Vaccine,
        'unit' => 'vials',
        'current_quantity' => 10,
        'unit_cost_minor' => 2_500_00,
    ]);

    $this->actingAs($owner)
        ->get(route('health.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('health/Index'));

    // Create health event
    $this->actingAs($owner)
        ->post(route('health.store', $team), [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'inventory_item_id' => $vaccine->id,
            'event_type' => HealthEventType::Medication->value,
            'title' => 'Antibiotic Course',
            'product_name' => 'Oxytetracycline',
            'scheduled_on' => now()->subDay()->toDateString(),
            'withdrawal_days' => 10,
            'cost' => '4500',
        ])
        ->assertRedirect();

    $event = HealthEvent::query()->where('team_id', $team->id)->where('title', 'Antibiotic Course')->firstOrFail();
    expect($event->isOverdue())->toBeTrue()
        ->and($event->cost_minor)->toBe(450_000);

    // Update outcome notes without changing status or inventory_quantity_used
    $this->actingAs($operator)
        ->patch(route('health.update', [$team, $event]), [
            'outcome_notes' => 'Follow-up check cleared',
        ])
        ->assertRedirect();

    expect($event->refresh()->outcome_notes)->toBe('Follow-up check cleared');

    // Operator completes event and deducts vaccine/med stock
    $this->actingAs($operator)
        ->patch(route('health.update', [$team, $event]), [
            'status' => HealthEventStatus::Completed->value,
            'cost' => '5000',
            'inventory_quantity_used' => 2,
            'outcome_notes' => 'Completed smoothly',
        ])
        ->assertRedirect();

    expect($event->refresh()->status)->toBe(HealthEventStatus::Completed)
        ->and($event->isUnderWithdrawal())->toBeTrue()
        ->and($event->cost_minor)->toBe(500_000)
        ->and((float) $vaccine->refresh()->current_quantity)->toBe(8.0);

    // Withdrawal surfaces on harvests page and health page
    $this->actingAs($owner)
        ->get(route('harvests.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('activeWithdrawals', 1));

    $this->actingAs($owner)
        ->get(route('health.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.activeWithdrawals', 1)
            ->where('summary.completed', 1)
        );

    // Apply preset schedule twice (second call skips duplicates)
    $this->actingAs($owner)
        ->post(route('farms.production-cycles.health-presets.store', [$team, $farm, $cycle]), [
            'preset_key' => 'poultry_broiler',
        ])
        ->assertRedirect();

    expect(HealthEvent::query()->where('production_cycle_id', $cycle->id)->count())->toBe(6);

    $this->actingAs($owner)
        ->post(route('farms.production-cycles.health-presets.store', [$team, $farm, $cycle]), [
            'preset_key' => 'poultry_broiler',
        ])
        ->assertRedirect();

    expect(HealthEvent::query()->where('production_cycle_id', $cycle->id)->count())->toBe(6);
});

test('health schedules enforce permissions and cross-team isolation', function () {
    [$owner, $operator, $investor, $team, $farm, $cycle] = healthContext();
    $otherTeam = Team::factory()->create();
    $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);
    $otherCycle = ProductionCycle::factory()->create(['team_id' => $otherTeam->id, 'farm_id' => $otherFarm->id]);
    $otherEvent = HealthEvent::factory()->create(['team_id' => $otherTeam->id, 'farm_id' => $otherFarm->id]);

    $this->actingAs($investor)->get(route('health.index', $team))->assertForbidden();
    $this->actingAs($operator)->post(route('health.store', $team), [])->assertForbidden();
    $this->actingAs($operator)->post(route('farms.production-cycles.health-presets.store', [$team, $farm, $cycle]), [
        'preset_key' => 'poultry_broiler',
    ])->assertForbidden();

    $this->actingAs($owner)->patch(route('health.update', [$team, $otherEvent]), [])->assertNotFound();
    $this->actingAs($owner)->post(route('farms.production-cycles.health-presets.store', [$team, $otherFarm, $otherCycle]), [
        'preset_key' => 'poultry_broiler',
    ])->assertNotFound();
});
