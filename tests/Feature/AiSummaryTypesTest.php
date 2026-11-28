<?php

use App\Ai\Agents\InternalFarmSummary;
use App\Enums\FarmTaskStatus;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Enums\WhatsappIntakeStatus;
use App\Jobs\GenerateAiSummary;
use App\Models\AiSummary;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;
use Spatie\Permission\Models\Permission;

function summaryTypesContext(): array
{
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $cycle = ProductionCycle::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);

    return [$team, $farm, $cycle];
}

test('weekly summaries use only eligible activity and task data from the last seven days', function () {
    $this->travelTo(now()->startOfDay());
    [$team, $farm, $cycle] = summaryTypesContext();
    $activity = FarmActivity::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'activity_date' => now()->subDays(6), 'investor_safe_summary' => 'Eligible recent update', 'internal_notes' => 'private note',
    ]);
    FarmActivity::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'activity_date' => now()->subDays(7), 'investor_safe_summary' => 'Old update',
    ]);
    $otherCycle = ProductionCycle::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);
    FarmActivity::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $otherCycle->id,
        'activity_date' => now(), 'investor_safe_summary' => 'Different cycle update',
    ]);
    $task = FarmTask::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'due_on' => now(), 'title' => 'Visible task', 'investor_visible' => true,
    ]);
    FarmTask::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'due_on' => now(), 'title' => 'Private task', 'investor_visible' => false,
    ]);
    $summary = AiSummary::factory()->create(['team_id' => $team->id, 'investor_agreement_id' => null, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'type' => 'weekly']);
    InternalFarmSummary::fake(['Weekly draft']);

    (new GenerateAiSummary($summary->id))->handle();

    InternalFarmSummary::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Eligible recent update')
        && $prompt->contains('Visible task') && ! $prompt->contains('Old update')
        && ! $prompt->contains('Different cycle update') && ! $prompt->contains('Private task')
        && ! $prompt->contains('private note'));
    expect($summary->fresh()->status)->toBe('draft')
        ->and($summary->fresh()->generated_text)->toBe('Weekly draft')
        ->and($summary->fresh()->source_records['activities'])->toBe([$activity->id])
        ->and($summary->fresh()->source_records['tasks'])->toBe([$task->id]);
});

test('risk summaries select only visible blocked or delayed tasks within the selected farm and cycle', function () {
    [$team, $farm, $cycle] = summaryTypesContext();
    $task = FarmTask::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'title' => 'Blocked irrigation', 'status' => FarmTaskStatus::Blocked, 'investor_visible' => true,
    ]);
    FarmTask::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'title' => 'Routine task', 'status' => FarmTaskStatus::Planned, 'investor_visible' => true,
    ]);
    FarmTask::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'title' => 'Internal blocker', 'status' => FarmTaskStatus::Delayed, 'investor_visible' => false,
    ]);
    $summary = AiSummary::factory()->create(['team_id' => $team->id, 'investor_agreement_id' => null, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'type' => 'risk_decision']);
    InternalFarmSummary::fake(['Risk draft']);

    (new GenerateAiSummary($summary->id))->handle();

    InternalFarmSummary::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Blocked irrigation')
        && ! $prompt->contains('Routine task') && ! $prompt->contains('Internal blocker'));
    expect($summary->fresh()->generated_text)->toBe('Risk draft')
        ->and($summary->fresh()->source_records['tasks'])->toBe([$task->id]);
});

test('intake cleanup sends only the provided sanitized excerpt for the selected pending record', function () {
    [$team, $farm, $cycle] = summaryTypesContext();
    $intake = WhatsappIntake::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'source_message' => 'Private original bank information', 'source_sender' => 'Private sender',
    ]);
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => null, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'type' => 'intake_cleanup',
        'whatsapp_intake_id' => $intake->id, 'input_text' => 'Sanitized field update',
    ]);
    InternalFarmSummary::fake(['Cleanup draft']);

    (new GenerateAiSummary($summary->id))->handle();

    InternalFarmSummary::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Sanitized field update')
        && ! $prompt->contains('Private original bank information') && ! $prompt->contains('Private sender'));
    expect($summary->fresh()->generated_text)->toBe('Cleanup draft')
        ->and($summary->fresh()->source_records['whatsapp_intakes'])->toBe([$intake->id]);
});

test('intake cleanup refuses records outside the selected farm and cycle or no longer pending', function (string $condition) {
    [$team, $farm, $cycle] = summaryTypesContext();
    $otherFarm = Farm::factory()->create(['team_id' => $team->id]);
    $otherCycle = ProductionCycle::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);
    $otherTeam = Team::factory()->create();
    $intake = WhatsappIntake::factory()->create([
        'team_id' => $condition === 'team' ? $otherTeam->id : $team->id,
        'farm_id' => $condition === 'farm' ? $otherFarm->id : $farm->id,
        'production_cycle_id' => $condition === 'cycle' ? $otherCycle->id : $cycle->id,
        'review_status' => $condition === 'status' ? WhatsappIntakeStatus::Rejected : WhatsappIntakeStatus::Pending,
    ]);
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => null, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'type' => 'intake_cleanup',
        'whatsapp_intake_id' => $intake->id, 'input_text' => 'Sanitized input',
    ]);
    InternalFarmSummary::fake();

    expect(fn () => (new GenerateAiSummary($summary->id))->handle())->toThrow(RuntimeException::class);
    InternalFarmSummary::assertNeverPrompted();
})->with(['team', 'farm', 'cycle', 'status']);

test('intake cleanup requires sanitized input and rejects unknown summary types', function () {
    [$team, $farm, $cycle] = summaryTypesContext();
    $intake = WhatsappIntake::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id]);
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => null, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'type' => 'intake_cleanup',
        'whatsapp_intake_id' => $intake->id, 'input_text' => '  ',
    ]);
    InternalFarmSummary::fake();

    expect(fn () => (new GenerateAiSummary($summary->id))->handle())->toThrow(RuntimeException::class);
    $summary->update(['type' => 'unsupported']);
    expect(fn () => (new GenerateAiSummary($summary->id))->handle())->toThrow(RuntimeException::class);
    InternalFarmSummary::assertNeverPrompted();
});

test('internal summaries require a farm owned by the team and an optional cycle of that farm', function (string $condition) {
    [$team, $farm, $cycle] = summaryTypesContext();
    $otherTeam = Team::factory()->create();
    $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);
    $otherCycle = ProductionCycle::factory()->create(['team_id' => $otherTeam->id, 'farm_id' => $otherFarm->id]);
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => null, 'type' => 'weekly',
        'farm_id' => match ($condition) {
            'missing' => null, 'team' => $otherFarm->id, default => $farm->id
        },
        'production_cycle_id' => $condition === 'cycle' ? $otherCycle->id : $cycle->id,
    ]);
    InternalFarmSummary::fake();

    expect(fn () => (new GenerateAiSummary($summary->id))->handle())->toThrow(RuntimeException::class);
    InternalFarmSummary::assertNeverPrompted();
})->with(['missing', 'team', 'cycle']);

test('internal summaries can cover a farm without selecting a cycle', function () {
    [$team, $farm, $cycle] = summaryTypesContext();
    $activity = FarmActivity::factory()->create([
        'team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
        'activity_date' => now()->toDateString(), 'investor_safe_summary' => 'Farm progress',
    ]);
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id, 'investor_agreement_id' => null, 'type' => 'weekly',
        'farm_id' => $farm->id, 'production_cycle_id' => null,
    ]);
    InternalFarmSummary::fake(['Farm draft']);

    (new GenerateAiSummary($summary->id))->handle();

    expect($summary->fresh()->generated_text)->toBe('Farm draft')
        ->and($summary->fresh()->source_records['activities'])->toBe([$activity->id])
        ->and($summary->fresh()->generation_context['investor_id'])->toBeNull();
});

test('farm managers can request internal summaries without investor agreements', function () {
    Queue::fake();
    [$team, $farm, $cycle] = summaryTypesContext();
    $manager = User::factory()->create();
    $team->members()->attach($manager, ['role' => TeamRole::Owner->value]);
    $manager->switchTeam($team);
    $otherFarm = Farm::factory()->create(['team_id' => $team->id]);
    $otherCycle = ProductionCycle::factory()->create(['team_id' => $team->id, 'farm_id' => $otherFarm->id]);

    $this->actingAs($manager)->post(route('ai-summaries.store', $team), [
        'type' => 'weekly', 'farm_id' => $farm->id, 'production_cycle_id' => $otherCycle->id,
    ])->assertSessionHasErrors('production_cycle_id');
    $this->post(route('ai-summaries.store', $team), [
        'type' => 'weekly', 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $summary = AiSummary::query()->sole();
    expect($summary->investor_agreement_id)->toBeNull()
        ->and($summary->farm_id)->toBe($farm->id)
        ->and($summary->production_cycle_id)->toBe($cycle->id);
    Queue::assertPushed(GenerateAiSummary::class);
});

test('intake requests require a sanitized excerpt and cannot select another farm', function () {
    Queue::fake();
    [$team, $farm, $cycle] = summaryTypesContext();
    $manager = User::factory()->create();
    $team->members()->attach($manager, ['role' => TeamRole::Owner->value]);
    $manager->switchTeam($team);
    $otherFarm = Farm::factory()->create(['team_id' => $team->id]);
    $foreignIntake = WhatsappIntake::factory()->create(['team_id' => $team->id, 'farm_id' => $otherFarm->id]);
    $intake = WhatsappIntake::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id]);
    $url = route('ai-summaries.store', $team);
    $input = ['type' => 'intake_cleanup', 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'whatsapp_intake_id' => $intake->id];
    $this->actingAs($manager)->post($url, $input)->assertSessionHasErrors('input_text');
    $this->post($url, [...$input, 'input_text' => 'Safe excerpt', 'whatsapp_intake_id' => $foreignIntake->id])->assertSessionHasErrors('whatsapp_intake_id');
    $this->post($url, [...$input, 'input_text' => 'Safe excerpt'])->assertSessionHasNoErrors()->assertRedirect();
    expect(AiSummary::query()->sole()->input_text)->toBe('Safe excerpt');
});

test('summary access respects separate investor and farm management permissions', function () {
    Queue::fake();
    [$team, $farm, $cycle] = summaryTypesContext();
    $agreement = InvestorAgreement::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id]);
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    Permission::findOrCreate(TeamPermission::ManageInvestorAgreements->value, 'web');
    setPermissionsTeamId($team->id);
    $member->givePermissionTo(TeamPermission::ManageInvestorAgreements->value);
    setPermissionsTeamId(null);
    AiSummary::factory()->create(['team_id' => $team->id, 'investor_agreement_id' => null, 'farm_id' => $farm->id, 'type' => 'weekly', 'status' => 'draft', 'generated_text' => 'Internal operations draft']);
    $this->actingAs($member)->get(route('ai-summaries.index', $team))->assertOk()->assertDontSee('Internal operations draft');
    $this->post(route('ai-summaries.store', $team), ['type' => 'weekly', 'farm_id' => $farm->id])->assertForbidden();
    $this->post(route('ai-summaries.store', $team), ['type' => 'investor_progress', 'agreement_id' => $agreement->id])->assertRedirect();
});

test('farm-only managers can review internal drafts without seeing investor summaries', function () {
    Queue::fake();
    [$team, $farm, $cycle] = summaryTypesContext();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    Permission::findOrCreate(TeamPermission::ManageFarmOperations->value, 'web');
    setPermissionsTeamId($team->id);
    $member->givePermissionTo(TeamPermission::ManageFarmOperations->value);
    setPermissionsTeamId(null);
    $agreement = InvestorAgreement::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'title' => 'Private agreement']);
    $progress = AiSummary::factory()->create(['team_id' => $team->id, 'investor_agreement_id' => $agreement->id, 'status' => 'draft', 'generated_text' => 'Investor draft']);
    $internal = AiSummary::factory()->create(['team_id' => $team->id, 'investor_agreement_id' => null, 'farm_id' => $farm->id, 'type' => 'weekly', 'status' => 'draft', 'generated_text' => 'Farm draft']);
    $this->actingAs($member)->get(route('ai-summaries.index', $team))->assertOk()->assertSee('Farm draft')->assertDontSee('Investor draft')->assertDontSee('Private agreement');
    $this->patch(route('ai-summaries.review', ['current_team' => $team, 'ai_summary' => $progress->id]), ['action' => 'approve', 'final_text' => 'Private'])->assertForbidden();
    $this->patch(route('ai-summaries.review', ['current_team' => $team, 'ai_summary' => $internal->id]), ['action' => 'approve', 'final_text' => 'Reviewed farm draft'])->assertRedirect()->assertSessionHasNoErrors();
    expect($internal->fresh()->final_text)->toBe('Reviewed farm draft');
});
