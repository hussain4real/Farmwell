<?php

use App\Enums\TeamRole;
use App\Models\AuditEvent;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;

test('team settings can be updated by owners and audited', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.settings.update', [$team, 'operations', 'defaults']), [
            'value' => [
                'currency' => 'NGN',
                'timezone' => 'Africa/Lagos',
            ],
            'reason' => 'Initial operating defaults',
        ]);

    $response->assertRedirect(route('teams.edit', $team));

    $setting = $team->settings()
        ->where('setting_group', 'operations')
        ->where('setting_key', 'defaults')
        ->firstOrFail();

    expect($setting->value)->toBe([
        'currency' => 'NGN',
        'timezone' => 'Africa/Lagos',
    ])
        ->and($setting->updated_by)->toBe($owner->id)
        ->and($setting->team->is($team))->toBeTrue()
        ->and($setting->updatedBy?->is($owner))->toBeTrue();

    $auditEvent = $team->auditEvents()->firstOrFail();

    expect($auditEvent->team_id)->toBe($team->id)
        ->and($auditEvent->actor_id)->toBe($owner->id)
        ->and($auditEvent->action)->toBe('team_setting.updated')
        ->and($auditEvent->subject_type)->toBe(TeamSetting::class)
        ->and($auditEvent->subject_id)->toBe($setting->id)
        ->and($auditEvent->team?->is($team))->toBeTrue()
        ->and($auditEvent->actor?->is($owner))->toBeTrue()
        ->and($auditEvent->subject?->is($setting))->toBeTrue()
        ->and($auditEvent->old_values)->toBe(['value' => null])
        ->and($auditEvent->new_values)->toBe([
            'setting_group' => 'operations',
            'setting_key' => 'defaults',
            'value' => [
                'currency' => 'NGN',
                'timezone' => 'Africa/Lagos',
            ],
        ])
        ->and($auditEvent->reason)->toBe('Initial operating defaults');
});

test('team settings can be updated by admins', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $response = $this
        ->actingAs($admin)
        ->patch(route('teams.settings.update', [$team, 'finance', 'defaults']), [
            'value' => [
                'currency' => 'USD',
            ],
        ]);

    $response->assertRedirect(route('teams.edit', $team));

    $this->assertDatabaseHas('team_settings', [
        'team_id' => $team->id,
        'setting_group' => 'finance',
        'setting_key' => 'defaults',
        'updated_by' => $admin->id,
    ]);
});

test('team settings cannot be updated by members or unrelated users', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $unrelatedUser = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($member)
        ->patch(route('teams.settings.update', [$team, 'operations', 'defaults']), [
            'value' => [
                'currency' => 'NGN',
            ],
        ])
        ->assertForbidden();

    $this
        ->actingAs($unrelatedUser)
        ->patch(route('teams.settings.update', [$team, 'operations', 'defaults']), [
            'value' => [
                'currency' => 'USD',
            ],
        ])
        ->assertForbidden();

    expect(TeamSetting::query()->where('team_id', $team->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('team_id', $team->id)->exists())->toBeFalse();
});
