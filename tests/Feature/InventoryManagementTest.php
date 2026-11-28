<?php

use App\Enums\InventoryCategory;
use App\Enums\InventoryMovementType;
use App\Enums\TeamRole;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function inventoryContext(): array
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
    $cycle = ProductionCycle::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);

    return [$owner, $operator, $investor, $team, $farm, $cycle];
}

test('inventory items and movements support purchases with optional finance expenses, usage deductions, adjustments, and low stock', function () {
    [$owner, $operator, , $team, $farm, $cycle] = inventoryContext();

    $this->actingAs($owner)
        ->get(route('inventory.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('inventory/Index'));

    // Create item
    $this->actingAs($owner)
        ->post(route('inventory.store', $team), [
            'farm_id' => $farm->id,
            'name' => '4mm Fish Feed',
            'category' => InventoryCategory::Feed->value,
            'unit' => 'bags',
            'unit_weight_kg' => 15,
            'current_quantity' => 5,
            'reorder_level' => 6,
            'unit_cost' => '25000',
            'supplier_name' => 'AquaFeeds Ltd',
        ])
        ->assertRedirect();

    $item = InventoryItem::query()->where('team_id', $team->id)->where('name', '4mm Fish Feed')->firstOrFail();
    expect($item->isLowStock())->toBeTrue()
        ->and($item->stockValueMinor())->toBe(125_000_00);

    // Update item
    $this->actingAs($owner)
        ->patch(route('inventory.update', [$team, $item]), [
            'reorder_level' => 4,
            'unit_cost' => '26000',
        ])
        ->assertRedirect();

    expect($item->refresh()->isLowStock())->toBeFalse()
        ->and($item->unit_cost_minor)->toBe(2_600_000);

    // Purchase with record_as_expense=true creates Expense
    $this->actingAs($owner)
        ->post(route('inventory.movements.store', [$team, $item]), [
            'movement_type' => InventoryMovementType::Purchase->value,
            'quantity' => 10,
            'unit_cost' => '27000',
            'movement_date' => now()->toDateString(),
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'record_as_expense' => true,
        ])
        ->assertRedirect();

    expect((float) $item->refresh()->current_quantity)->toBe(15.0)
        ->and($item->unit_cost_minor)->toBe(2_700_000)
        ->and(Expense::query()->where('team_id', $team->id)->where('amount_minor', 27_000_000)->exists())->toBeTrue();

    // Operator can record usage
    $this->actingAs($operator)
        ->post(route('inventory.movements.store', [$team, $item]), [
            'movement_type' => InventoryMovementType::Usage->value,
            'quantity' => 5,
            'movement_date' => now()->toDateString(),
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
        ])
        ->assertRedirect();

    expect((float) $item->refresh()->current_quantity)->toBe(10.0);

    // Insufficient stock rejected
    $this->actingAs($operator)
        ->post(route('inventory.movements.store', [$team, $item]), [
            'movement_type' => InventoryMovementType::Usage->value,
            'quantity' => 99,
            'movement_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('quantity');

    // Adjustment in & out + over-stock out error
    $this->actingAs($owner)
        ->post(route('inventory.movements.store', [$team, $item]), [
            'movement_type' => InventoryMovementType::Adjustment->value,
            'adjustment_direction' => 'in',
            'quantity' => 2,
            'movement_date' => now()->toDateString(),
        ])
        ->assertRedirect();
    expect((float) $item->refresh()->current_quantity)->toBe(12.0);

    $this->actingAs($owner)
        ->post(route('inventory.movements.store', [$team, $item]), [
            'movement_type' => InventoryMovementType::Adjustment->value,
            'adjustment_direction' => 'out',
            'quantity' => 3,
            'movement_date' => now()->toDateString(),
        ])
        ->assertRedirect();
    expect((float) $item->refresh()->current_quantity)->toBe(9.0);

    $this->actingAs($owner)
        ->post(route('inventory.movements.store', [$team, $item]), [
            'movement_type' => InventoryMovementType::Adjustment->value,
            'adjustment_direction' => 'out',
            'quantity' => 50,
            'movement_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('quantity');

    $this->actingAs($owner)
        ->post(route('inventory.store', $team), [
            'farm_id' => $farm->id,
            'name' => 'Water Test Kit',
            'category' => InventoryCategory::Equipment->value,
            'unit' => 'kits',
        ])
        ->assertRedirect();

    $unpriced = InventoryItem::query()->where('team_id', $team->id)->where('name', 'Water Test Kit')->firstOrFail();
    expect($unpriced->unit_cost_minor)->toBe(0);

    $this->actingAs($owner)
        ->patch(route('inventory.update', [$team, $unpriced]), [
            'unit_cost' => null,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('inventory.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 2)
            ->has('movements', 4)
        );
});

test('inventory enforces permissions and cross-team isolation', function () {
    [$owner, $operator, $investor, $team] = inventoryContext();
    $item = InventoryItem::factory()->create(['team_id' => $team->id]);

    $otherTeam = Team::factory()->create();
    $otherItem = InventoryItem::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($investor)->get(route('inventory.index', $team))->assertForbidden();
    $this->actingAs($operator)->post(route('inventory.store', $team), [])->assertForbidden();
    $this->actingAs($operator)->patch(route('inventory.update', [$team, $item]), [])->assertForbidden();
    $this->actingAs($operator)->post(route('inventory.movements.store', [$team, $item]), [
        'movement_type' => InventoryMovementType::Purchase->value,
        'quantity' => 1,
        'movement_date' => now()->toDateString(),
    ])->assertForbidden();

    $this->actingAs($owner)->patch(route('inventory.update', [$team, $otherItem]), [])->assertNotFound();
});
