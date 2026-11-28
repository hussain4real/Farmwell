<?php

use App\Actions\FarmOperations\BuildDailyOperationalReminders;
use App\Enums\FarmTaskStatus;
use App\Enums\FarmType;
use App\Enums\HealthEventStatus;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionLogDomain;
use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\FarmTask;
use App\Models\HealthEvent;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;
use App\Notifications\FarmOperations\DailyFarmRemindersNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('daily operational reminders aggregate health, withdrawal, stock, water, and task alerts', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Ogun Agro Holdings']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    // Empty team has no alerts
    $emptyDigest = app(BuildDailyOperationalReminders::class)->handle($team);
    expect($emptyDigest['hasAlerts'])->toBeFalse();

    $farm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Aquaculture]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'farm_type' => FarmType::Aquaculture,
        'status' => ProductionCycleStatus::Active,
    ]);

    // Water alert (low DO + high ammonia)
    ProductionLog::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'domain' => ProductionLogDomain::Aquaculture,
        'dissolved_oxygen_mg_l' => 2.5,
        'ammonia_mg_l' => 1.2,
    ]);

    // Overdue & due-soon health events + active withdrawal
    HealthEvent::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'title' => 'Overdue Pond Salt Flush',
        'status' => HealthEventStatus::Scheduled,
        'scheduled_on' => now()->subDay()->toDateString(),
    ]);
    HealthEvent::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'title' => 'Vitamin C Stress Pack',
        'status' => HealthEventStatus::Scheduled,
        'scheduled_on' => now()->addDay()->toDateString(),
    ]);
    HealthEvent::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'title' => 'Oxytetracycline Bath',
        'status' => HealthEventStatus::Completed,
        'completed_on' => now()->toDateString(),
        'withdrawal_days' => 5,
    ]);

    // Low stock item
    InventoryItem::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => '6mm Coppens Feed',
        'current_quantity' => 2,
        'reorder_level' => 5,
    ]);

    // Overdue task
    FarmTask::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'status' => FarmTaskStatus::Planned,
        'due_on' => now()->subDays(2)->toDateString(),
    ]);

    $digest = app(BuildDailyOperationalReminders::class)->handle($team);

    expect($digest['hasAlerts'])->toBeTrue()
        ->and($digest['overdueHealthEvents'])->toHaveCount(1)
        ->and($digest['dueSoonHealthEvents'])->toHaveCount(1)
        ->and($digest['activeWithdrawals'])->toHaveCount(1)
        ->and($digest['lowStockItems'])->toHaveCount(1)
        ->and($digest['waterQualityAlerts'])->toHaveCount(1)
        ->and($digest['overdueTasksCount'])->toBe(1)
        ->and($digest['whatsappText'])->toContain('Ogun Agro Holdings')
        ->and($digest['whatsappText'])->toContain('Overdue Pond Salt Flush')
        ->and($digest['whatsappText'])->toContain('6mm Coppens Feed');

    $mail = (new DailyFarmRemindersNotification($team, $digest))->toMail($owner);
    expect($mail->subject)->toContain('Ogun Agro Holdings')
        ->and($mail->introLines)->not->toBeEmpty();
});

test('daily farm reminders command notifies verified team operators and respects team setting toggles', function () {
    Notification::fake();

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $operator = User::factory()->create(['email_verified_at' => now()]);
    $unverifiedAdmin = User::factory()->create(['email_verified_at' => null]);
    $investor = User::factory()->create(['email_verified_at' => now()]);

    $team = Team::factory()->create(['slug' => 'alert-team']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($operator, ['role' => TeamRole::Operator->value]);
    $team->members()->attach($unverifiedAdmin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);

    $farm = Farm::factory()->create(['team_id' => $team->id]);
    InventoryItem::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'current_quantity' => 1,
        'reorder_level' => 5,
    ]);

    // Team with no alerts
    $cleanTeam = Team::factory()->create(['slug' => 'clean-team']);
    $cleanOwner = User::factory()->create(['email_verified_at' => now()]);
    $cleanTeam->members()->attach($cleanOwner, ['role' => TeamRole::Owner->value]);

    $this->artisan('farmwell:send-daily-reminders', ['--team' => 'alert-team'])
        ->assertSuccessful();

    Notification::assertSentTo([$owner, $operator], DailyFarmRemindersNotification::class);
    Notification::assertNotSentTo([$unverifiedAdmin, $investor, $cleanOwner], DailyFarmRemindersNotification::class);

    // Clean team with zero alerts is skipped when running without filter
    Notification::fake();
    $this->artisan('farmwell:send-daily-reminders')->assertSuccessful();
    Notification::assertSentTo([$owner, $operator], DailyFarmRemindersNotification::class);
    Notification::assertNotSentTo([$cleanOwner], DailyFarmRemindersNotification::class);

    // Team Settings pause toggle disables notifications
    Notification::fake();
    $owner->switchTeam($team);
    $this->actingAs($owner)
        ->patch(route('teams.settings.update', [$team, 'operations', 'daily_reminders']), [
            'value' => ['enabled' => false],
            'reason' => 'Pause during off-season',
        ])
        ->assertRedirect(route('teams.edit', $team));

    expect(TeamSetting::query()->where('team_id', $team->id)->where('setting_key', 'daily_reminders')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('teams.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('dailyRemindersEnabled', false));

    $this->artisan('farmwell:send-daily-reminders', ['--team' => 'alert-team'])
        ->assertSuccessful();

    Notification::assertNothingSent();
});
