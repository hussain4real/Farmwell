<?php

use App\Enums\FarmActivityStatus;
use App\Enums\FarmTaskStatus;
use App\Enums\WhatsappIntakeStatus;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('phase three enums expose labels options and reason rules', function () {
    expect(FarmActivityStatus::options())->toContain(
        ['value' => FarmActivityStatus::Completed->value, 'label' => 'Completed'],
        ['value' => FarmActivityStatus::Changed->value, 'label' => 'Changed'],
    )
        ->and(FarmTaskStatus::options())->toContain(
            ['value' => FarmTaskStatus::Delayed->value, 'label' => 'Delayed'],
            ['value' => FarmTaskStatus::Cancelled->value, 'label' => 'Cancelled'],
        )
        ->and(FarmTaskStatus::Delayed->requiresReason())->toBeTrue()
        ->and(FarmTaskStatus::Blocked->requiresReason())->toBeTrue()
        ->and(FarmTaskStatus::Skipped->requiresReason())->toBeTrue()
        ->and(FarmTaskStatus::Cancelled->requiresReason())->toBeTrue()
        ->and(FarmTaskStatus::Planned->requiresReason())->toBeFalse()
        ->and(FarmTaskStatus::InProgress->requiresReason())->toBeFalse()
        ->and(FarmTaskStatus::Completed->requiresReason())->toBeFalse()
        ->and(WhatsappIntakeStatus::options())->toContain(
            ['value' => WhatsappIntakeStatus::Pending->value, 'label' => 'Pending review'],
            ['value' => WhatsappIntakeStatus::NeedsClarification->value, 'label' => 'Needs clarification'],
        );
});

test('phase three models expose tenant relationships casts and media collections', function () {
    $team = Team::factory()->create();
    $actor = User::factory()->create();
    $reviewer = User::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $unit = ProductionUnit::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $commodity = Commodity::factory()->create(['team_id' => $team->id]);

    $activity = FarmActivity::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_unit_id' => $unit->id,
        'production_cycle_id' => $cycle->id,
        'commodity_id' => $commodity->id,
        'recorded_by_id' => $actor->id,
        'cost' => '88.5',
        'status' => FarmActivityStatus::Delayed,
    ]);
    $task = FarmTask::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_unit_id' => $unit->id,
        'production_cycle_id' => $cycle->id,
        'assigned_to_id' => $actor->id,
        'created_by_id' => $reviewer->id,
        'completed_by_id' => $actor->id,
        'status' => FarmTaskStatus::Completed,
        'investor_visible' => true,
    ]);
    $intake = WhatsappIntake::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_unit_id' => $unit->id,
        'production_cycle_id' => $cycle->id,
        'commodity_id' => $commodity->id,
        'imported_by_id' => $actor->id,
        'reviewer_id' => $reviewer->id,
        'converted_activity_id' => $activity->id,
        'normalized_cost' => '120.5',
        'review_status' => WhatsappIntakeStatus::Converted,
    ]);

    expect($team->farmActivities()->first()?->is($activity))->toBeTrue()
        ->and($team->farmTasks()->first()?->is($task))->toBeTrue()
        ->and($team->whatsappIntakes()->first()?->is($intake))->toBeTrue()
        ->and($farm->farmActivities()->first()?->is($activity))->toBeTrue()
        ->and($farm->farmTasks()->first()?->is($task))->toBeTrue()
        ->and($unit->farmActivities()->first()?->is($activity))->toBeTrue()
        ->and($unit->farmTasks()->first()?->is($task))->toBeTrue()
        ->and($cycle->farmActivities()->first()?->is($activity))->toBeTrue()
        ->and($cycle->farmTasks()->first()?->is($task))->toBeTrue()
        ->and($activity->team->is($team))->toBeTrue()
        ->and($activity->farm->is($farm))->toBeTrue()
        ->and($activity->productionUnit?->is($unit))->toBeTrue()
        ->and($activity->productionCycle?->is($cycle))->toBeTrue()
        ->and($activity->commodity?->is($commodity))->toBeTrue()
        ->and($activity->recordedBy?->is($actor))->toBeTrue()
        ->and($activity->cost)->toBe('88.50')
        ->and($activity->status)->toBe(FarmActivityStatus::Delayed)
        ->and($task->team->is($team))->toBeTrue()
        ->and($task->farm->is($farm))->toBeTrue()
        ->and($task->productionUnit?->is($unit))->toBeTrue()
        ->and($task->productionCycle?->is($cycle))->toBeTrue()
        ->and($task->assignedTo?->is($actor))->toBeTrue()
        ->and($task->createdBy?->is($reviewer))->toBeTrue()
        ->and($task->completedBy?->is($actor))->toBeTrue()
        ->and($task->status)->toBe(FarmTaskStatus::Completed)
        ->and($task->investor_visible)->toBeTrue()
        ->and($intake->team->is($team))->toBeTrue()
        ->and($intake->farm?->is($farm))->toBeTrue()
        ->and($intake->productionUnit?->is($unit))->toBeTrue()
        ->and($intake->productionCycle?->is($cycle))->toBeTrue()
        ->and($intake->commodity?->is($commodity))->toBeTrue()
        ->and($intake->importedBy?->is($actor))->toBeTrue()
        ->and($intake->reviewer?->is($reviewer))->toBeTrue()
        ->and($intake->convertedActivity?->is($activity))->toBeTrue()
        ->and($intake->normalized_cost)->toBe('120.50')
        ->and($intake->review_status)->toBe(WhatsappIntakeStatus::Converted);
});
