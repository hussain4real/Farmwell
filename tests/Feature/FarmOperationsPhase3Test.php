<?php

use App\Enums\FarmActivityStatus;
use App\Enums\FarmTaskStatus;
use App\Enums\FarmType;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionUnitType;
use App\Enums\TeamRole;
use App\Enums\WhatsappIntakeStatus;
use App\Models\AuditEvent;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function farmwellPhaseThreeContext(TeamRole $role = TeamRole::Owner): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create(['name' => fake()->unique()->company().' Field Ops']);

    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    $farm = Farm::factory()->create([
        'team_id' => $team->id,
        'name' => 'North Field Farm',
        'farm_type' => FarmType::Crop,
    ]);
    $unit = ProductionUnit::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Block A',
        'unit_type' => ProductionUnitType::Field,
    ]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Tomato 2026',
        'farm_type' => FarmType::Crop,
        'status' => ProductionCycleStatus::Active,
    ]);
    $commodity = Commodity::factory()->create([
        'team_id' => $team->id,
        'name' => 'Tomato',
        'farm_type' => FarmType::Crop,
    ]);

    return [$user, $team, $farm, $unit, $cycle, $commodity];
}

function farmwellPhaseThreeRoute(string $name, Team $team, array $parameters = []): string
{
    return route($name, ['current_team' => $team, ...$parameters]);
}

test('activities can be recorded with private evidence and downloaded by the tenant', function () {
    Storage::fake('farmwell_private');

    [$owner, $team, $farm, $unit, $cycle, $commodity] = farmwellPhaseThreeContext();

    $this
        ->actingAs($owner)
        ->post(farmwellPhaseThreeRoute('farm-activities.store', $team), [
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'production_cycle_id' => $cycle->id,
            'commodity_id' => $commodity->id,
            'activity_date' => '2026-05-31',
            'activity_type' => 'Planting',
            'description' => 'Seedlings transplanted after bed preparation.',
            'inputs_used' => 'Seedlings and compost',
            'labour_used' => 'Four field hands',
            'cost' => '1250.50',
            'remarks' => 'Beds were moist.',
            'next_activity' => 'Irrigation check',
            'status' => FarmActivityStatus::Completed->value,
            'investor_safe_summary' => 'Planting completed on Block A.',
            'evidence_caption' => 'Planting proof',
            'evidence_visibility' => 'investor_visible',
            'evidence' => [
                UploadedFile::fake()->create('planting-proof.pdf', 128, 'application/pdf'),
            ],
        ])
        ->assertRedirect(farmwellPhaseThreeRoute('farms.index', $team));

    $activity = FarmActivity::query()->firstOrFail();
    $media = $activity->getFirstMedia(FarmActivity::EvidenceCollection);

    expect($activity->team_id)->toBe($team->id)
        ->and($activity->farm_id)->toBe($farm->id)
        ->and($activity->production_unit_id)->toBe($unit->id)
        ->and($activity->production_cycle_id)->toBe($cycle->id)
        ->and($activity->commodity_id)->toBe($commodity->id)
        ->and($activity->cost)->toBe('1250.50')
        ->and($activity->next_activity)->toBe('Irrigation check')
        ->and($activity->status)->toBe(FarmActivityStatus::Completed)
        ->and($media)->not->toBeNull()
        ->and($media?->disk)->toBe('farmwell_private')
        ->and($media?->getCustomProperty('caption'))->toBe('Planting proof')
        ->and($media?->getCustomProperty('visibility'))->toBe('investor_visible')
        ->and($media?->getCustomProperty('uploaded_by_id'))->toBe($owner->id);

    Storage::disk('farmwell_private')->assertExists($media->getPathRelativeToRoot());

    $this
        ->actingAs($owner)
        ->get(farmwellPhaseThreeRoute('farm-evidence.show', $team, ['media' => $media]))
        ->assertDownload($media->file_name);

    $otherTeam = Team::factory()->create(['name' => 'Other Team']);
    $otherTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this
        ->actingAs($owner)
        ->get(farmwellPhaseThreeRoute('farm-evidence.show', $otherTeam, ['media' => $media]))
        ->assertNotFound();

    $this
        ->actingAs($owner)
        ->get(farmwellPhaseThreeRoute('farms.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('farms/Index')
            ->where('stats.activities', 1)
            ->where('latestActivities.0.activityType', 'Planting')
            ->where('latestActivities.0.evidence.0.caption', 'Planting proof')
        );

    expect(AuditEvent::query()->where('team_id', $team->id)->pluck('action')->all())
        ->toContain('farm_activity.recorded');
});

test('tasks require delay reasons and status changes are audited', function () {
    [$owner, $team, $farm, $unit, $cycle] = farmwellPhaseThreeContext();
    $assignee = User::factory()->create();
    $team->members()->attach($assignee, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($owner)
        ->post(farmwellPhaseThreeRoute('farm-tasks.store', $team), [
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'production_cycle_id' => $cycle->id,
            'assigned_to_id' => $assignee->id,
            'title' => 'Check irrigation',
            'activity_type' => 'Inspection',
            'description' => 'Verify irrigation lines before noon.',
            'planned_for' => '2026-06-01',
            'due_on' => '2026-06-02',
            'status' => FarmTaskStatus::Delayed->value,
        ])
        ->assertSessionHasErrors('status_reason');

    $this
        ->actingAs($owner)
        ->post(farmwellPhaseThreeRoute('farm-tasks.store', $team), [
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'production_cycle_id' => $cycle->id,
            'assigned_to_id' => $assignee->id,
            'title' => 'Check irrigation',
            'activity_type' => 'Inspection',
            'description' => 'Verify irrigation lines before noon.',
            'planned_for' => '2026-06-01',
            'due_on' => '2026-06-02',
            'reminder_at' => '2026-06-01 08:00:00',
            'status' => FarmTaskStatus::Planned->value,
            'investor_visible' => true,
        ])
        ->assertRedirect(farmwellPhaseThreeRoute('farms.index', $team));

    $task = FarmTask::query()->where('title', 'Check irrigation')->firstOrFail();

    $this
        ->actingAs($owner)
        ->patch(farmwellPhaseThreeRoute('farm-tasks.status.update', $team, ['farm_task' => $task]), [
            'status' => FarmTaskStatus::Delayed->value,
            'status_reason' => 'Pump repair is pending.',
        ])
        ->assertRedirect(farmwellPhaseThreeRoute('farms.index', $team));

    expect($task->refresh()->status)->toBe(FarmTaskStatus::Delayed)
        ->and($task->status_reason)->toBe('Pump repair is pending.')
        ->and($task->completed_at)->toBeNull()
        ->and($task->completed_by_id)->toBeNull()
        ->and($task->investor_visible)->toBeTrue();

    $this
        ->actingAs($owner)
        ->get(farmwellPhaseThreeRoute('farms.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.openTasks', 1)
            ->where('upcomingTasks.0.title', 'Check irrigation')
            ->where('upcomingTasks.0.statusReason', 'Pump repair is pending.')
        );

    $this
        ->actingAs($owner)
        ->patch(farmwellPhaseThreeRoute('farm-tasks.status.update', $team, ['farm_task' => $task]), [
            'status' => FarmTaskStatus::Completed->value,
        ])
        ->assertRedirect(farmwellPhaseThreeRoute('farms.index', $team));

    expect($task->refresh()->status)->toBe(FarmTaskStatus::Completed)
        ->and($task->completed_at)->not->toBeNull()
        ->and($task->completed_by_id)->toBe($owner->id);

    expect(AuditEvent::query()->where('team_id', $team->id)->pluck('action')->all())
        ->toContain('farm_task.created', 'farm_task.status_updated');
});

test('whatsapp intake can be converted into official activities or rejected', function () {
    Storage::fake('farmwell_private');

    [$owner, $team, $farm, $unit, $cycle, $commodity] = farmwellPhaseThreeContext();

    $this
        ->actingAs($owner)
        ->post(farmwellPhaseThreeRoute('whatsapp-intakes.store', $team), [
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'production_cycle_id' => $cycle->id,
            'commodity_id' => $commodity->id,
            'source_message' => 'We applied compost to Block A today. Cost 900.',
            'source_sender' => 'Field Lead',
            'source_date' => '2026-05-31',
            'normalized_activity_date' => '2026-05-31',
            'normalized_activity_type' => 'Fertilizer',
            'normalized_description' => 'Compost applied to Block A.',
            'normalized_cost' => '900',
            'normalized_next_activity' => 'Water the beds',
            'normalized_investor_safe_summary' => 'Compost applied on schedule.',
            'evidence_caption' => 'Forwarded receipt',
            'evidence' => [
                UploadedFile::fake()->create('compost-receipt.pdf', 64, 'application/pdf'),
            ],
        ])
        ->assertRedirect(farmwellPhaseThreeRoute('farms.index', $team));

    $intake = WhatsappIntake::query()->firstOrFail();
    $intakeMedia = $intake->getFirstMedia(WhatsappIntake::EvidenceCollection);

    expect($intake->review_status)->toBe(WhatsappIntakeStatus::Pending)
        ->and($intakeMedia)->not->toBeNull()
        ->and($intakeMedia?->getCustomProperty('caption'))->toBe('Forwarded receipt')
        ->and($intakeMedia?->getCustomProperty('visibility'))->toBe('private');

    $this
        ->actingAs($owner)
        ->get(farmwellPhaseThreeRoute('farms.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.pendingIntakes', 1)
            ->where('pendingIntakes.0.sourceSender', 'Field Lead')
            ->where('pendingIntakes.0.evidence.0.caption', 'Forwarded receipt')
        );

    $this
        ->actingAs($owner)
        ->patch(farmwellPhaseThreeRoute('whatsapp-intakes.convert', $team, ['whatsapp_intake' => $intake]), [
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'production_cycle_id' => $cycle->id,
            'commodity_id' => $commodity->id,
            'activity_date' => '2026-05-31',
            'activity_type' => 'Fertilizer',
            'description' => 'Compost applied to Block A.',
            'cost' => '900',
            'next_activity' => 'Water the beds',
            'investor_safe_summary' => 'Compost applied on schedule.',
            'status' => FarmActivityStatus::Completed->value,
        ])
        ->assertRedirect(farmwellPhaseThreeRoute('farms.index', $team));

    $activity = FarmActivity::query()
        ->where('source_type', 'whatsapp_intake')
        ->where('source_reference_id', $intake->id)
        ->firstOrFail();

    expect($intake->refresh()->review_status)->toBe(WhatsappIntakeStatus::Converted)
        ->and($intake->reviewer_id)->toBe($owner->id)
        ->and($intake->converted_activity_id)->toBe($activity->id)
        ->and($intake->normalized_activity_type)->toBe('Fertilizer')
        ->and($activity->activity_type)->toBe('Fertilizer')
        ->and($activity->status)->toBe(FarmActivityStatus::Completed)
        ->and($activity->getMedia(FarmActivity::EvidenceCollection))->toHaveCount(1);

    $rejectableIntake = WhatsappIntake::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'imported_by_id' => $owner->id,
        'source_message' => 'Duplicate message.',
        'review_status' => WhatsappIntakeStatus::Pending,
    ]);

    $this
        ->actingAs($owner)
        ->patch(farmwellPhaseThreeRoute('whatsapp-intakes.reject', $team, ['whatsapp_intake' => $rejectableIntake]), [
            'rejection_reason' => 'Duplicate of converted update.',
        ])
        ->assertRedirect(farmwellPhaseThreeRoute('farms.index', $team));

    expect($rejectableIntake->refresh()->review_status)->toBe(WhatsappIntakeStatus::Rejected)
        ->and($rejectableIntake->reviewer_id)->toBe($owner->id)
        ->and($rejectableIntake->rejection_reason)->toBe('Duplicate of converted update.');

    expect(AuditEvent::query()->where('team_id', $team->id)->pluck('action')->all())
        ->toContain(
            'whatsapp_intake.created',
            'farm_activity.recorded',
            'whatsapp_intake.converted',
            'whatsapp_intake.rejected',
        );
});

test('phase three validation keeps field records scoped to the current team and farm', function () {
    [$owner, $team, $farm] = farmwellPhaseThreeContext();
    $otherTeam = Team::factory()->create(['name' => 'Other Field Ops']);
    $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);
    $otherUnit = ProductionUnit::factory()->create([
        'team_id' => $otherTeam->id,
        'farm_id' => $otherFarm->id,
    ]);
    $otherCycle = ProductionCycle::factory()->create([
        'team_id' => $otherTeam->id,
        'farm_id' => $otherFarm->id,
    ]);
    $otherTask = FarmTask::factory()->create([
        'team_id' => $otherTeam->id,
        'farm_id' => $otherFarm->id,
    ]);
    $otherIntake = WhatsappIntake::factory()->create([
        'team_id' => $otherTeam->id,
        'farm_id' => $otherFarm->id,
        'review_status' => WhatsappIntakeStatus::Pending,
    ]);

    $this
        ->actingAs($owner)
        ->post(farmwellPhaseThreeRoute('farm-activities.store', $team), [
            'farm_id' => $otherFarm->id,
            'activity_date' => '2026-05-31',
            'activity_type' => 'Inspection',
            'description' => 'Wrong team farm should fail.',
        ])
        ->assertSessionHasErrors('farm_id');

    $this
        ->actingAs($owner)
        ->post(farmwellPhaseThreeRoute('farm-tasks.store', $team), [
            'farm_id' => $farm->id,
            'production_unit_id' => $otherUnit->id,
            'production_cycle_id' => $otherCycle->id,
            'assigned_to_id' => User::factory()->create()->id,
            'title' => 'Wrong farm task',
            'due_on' => '2026-06-01',
        ])
        ->assertSessionHasErrors(['production_unit_id', 'production_cycle_id', 'assigned_to_id']);

    $this
        ->actingAs($owner)
        ->patch(farmwellPhaseThreeRoute('farm-tasks.status.update', $team, ['farm_task' => $otherTask]), [
            'status' => FarmTaskStatus::Completed->value,
        ])
        ->assertNotFound();

    $this
        ->actingAs($owner)
        ->post(farmwellPhaseThreeRoute('whatsapp-intakes.store', $team), [
            'farm_id' => $farm->id,
            'production_unit_id' => $otherUnit->id,
            'production_cycle_id' => $otherCycle->id,
            'source_message' => 'Wrong farm intake should fail.',
        ])
        ->assertSessionHasErrors(['production_unit_id', 'production_cycle_id']);

    $this
        ->actingAs($owner)
        ->patch(farmwellPhaseThreeRoute('whatsapp-intakes.convert', $team, ['whatsapp_intake' => $otherIntake]), [
            'farm_id' => $farm->id,
            'activity_date' => '2026-05-31',
            'activity_type' => 'Inspection',
            'description' => 'Wrong team intake should fail.',
        ])
        ->assertNotFound();
});
