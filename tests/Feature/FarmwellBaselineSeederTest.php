<?php

use App\Actions\Finance\EnsureDefaultExpenseCategories;
use App\Actions\Investors\ResolveInvestorApprovalSettings;
use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalTriggerType;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\ApprovalRule;
use App\Models\ExpenseCategory;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FarmwellBaselineSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('farmwell baseline seeder creates only required baseline records', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this->seed(FarmwellBaselineSeeder::class);

    expect(Permission::query()->pluck('name')->all())
        ->toEqualCanonicalizing(array_map(
            fn (TeamPermission $permission): string => $permission->value,
            TeamPermission::cases(),
        ))
        ->and(Role::query()->pluck('name')->all())
        ->toEqualCanonicalizing(array_map(
            fn (TeamRole $role): string => $role->value,
            TeamRole::cases(),
        ));

    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($team->id);

    try {
        expect($admin->fresh()->hasRole(TeamRole::Admin->value))->toBeTrue();
    } finally {
        setPermissionsTeamId($previousTeamId);
    }

    expect(TeamSetting::query()
        ->where('team_id', $team->id)
        ->where('setting_group', 'finance')
        ->where('setting_key', 'default_currency')
        ->sole()
        ->value)->toBe(['currency' => ResolveInvestorApprovalSettings::DefaultCurrency])
        ->and(TeamSetting::query()
            ->where('team_id', $team->id)
            ->where('setting_group', 'approvals')
            ->where('setting_key', 'investor_expense_threshold')
            ->sole()
            ->value)->toBe([
                'currency' => ResolveInvestorApprovalSettings::DefaultCurrency,
                'threshold_amount_minor' => ResolveInvestorApprovalSettings::DefaultExpenseThresholdMinor,
            ])
        ->and(ExpenseCategory::query()->where('team_id', $team->id)->count())->toBe(count(EnsureDefaultExpenseCategories::Defaults))
        ->and(ExpenseCategory::query()
            ->where('team_id', $team->id)
            ->where('requires_investor_approval', true)
            ->exists())->toBeFalse()
        ->and(ApprovalRule::query()
            ->where('team_id', $team->id)
            ->where('request_type', ApprovalRequestType::Expense)
            ->where('trigger_type', ApprovalTriggerType::Threshold)
            ->whereNull('investor_agreement_id')
            ->whereNull('expense_category_id')
            ->whereNull('funding_phase_id')
            ->where('threshold_amount_minor', ResolveInvestorApprovalSettings::DefaultExpenseThresholdMinor)
            ->where('currency', ResolveInvestorApprovalSettings::DefaultCurrency)
            ->exists())->toBeTrue()
        ->and(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('farmwell baseline seeder is idempotent and preserves configured team defaults', function () {
    $team = Team::factory()->create();

    TeamSetting::query()->create([
        'team_id' => $team->id,
        'setting_group' => 'finance',
        'setting_key' => 'default_currency',
        'value' => ['currency' => 'USD'],
    ]);

    TeamSetting::query()->create([
        'team_id' => $team->id,
        'setting_group' => 'approvals',
        'setting_key' => 'investor_expense_threshold',
        'value' => [
            'currency' => 'USD',
            'threshold_amount_minor' => 250_000_00,
        ],
    ]);

    $this->seed(FarmwellBaselineSeeder::class);
    $this->seed(FarmwellBaselineSeeder::class);

    expect(TeamSetting::query()
        ->where('team_id', $team->id)
        ->where('setting_group', 'finance')
        ->where('setting_key', 'default_currency')
        ->sole()
        ->value)->toBe(['currency' => 'USD'])
        ->and(TeamSetting::query()
            ->where('team_id', $team->id)
            ->where('setting_group', 'approvals')
            ->where('setting_key', 'investor_expense_threshold')
            ->sole()
            ->value)->toBe([
                'currency' => 'USD',
                'threshold_amount_minor' => 250_000_00,
            ])
        ->and(ExpenseCategory::query()->where('team_id', $team->id)->count())->toBe(count(EnsureDefaultExpenseCategories::Defaults))
        ->and(ApprovalRule::query()
            ->where('team_id', $team->id)
            ->where('request_type', ApprovalRequestType::Expense)
            ->where('trigger_type', ApprovalTriggerType::Threshold)
            ->whereNull('investor_agreement_id')
            ->whereNull('expense_category_id')
            ->whereNull('funding_phase_id')
            ->count())->toBe(1)
        ->and(ApprovalRule::query()
            ->where('team_id', $team->id)
            ->sole()
            ->only(['threshold_amount_minor', 'currency']))->toBe([
                'threshold_amount_minor' => 250_000_00,
                'currency' => 'USD',
            ]);
});

test('database seeder delegates to farmwell baseline without creating demo users', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(0)
        ->and(Role::query()->count())->toBe(count(TeamRole::cases()))
        ->and(Permission::query()->count())->toBe(count(TeamPermission::cases()));
});
