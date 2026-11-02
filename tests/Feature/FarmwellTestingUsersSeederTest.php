<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\FarmwellTestingUsersSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('testing users seeder creates an idempotent login for every team role with matching permissions', function () {
    $this->seed(FarmwellTestingUsersSeeder::class);
    $this->seed(FarmwellTestingUsersSeeder::class);

    $team = Team::query()->where('slug', FarmwellTestingUsersSeeder::TeamSlug)->sole();

    expect($team->name)->toBe(FarmwellTestingUsersSeeder::TeamName)
        ->and($team->is_personal)->toBeFalse()
        ->and(Team::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(count(TeamRole::cases()))
        ->and(Membership::query()->where('team_id', $team->id)->count())->toBe(count(TeamRole::cases()))
        ->and(Role::query()->count())->toBe(count(TeamRole::cases()))
        ->and(Permission::query()->count())->toBe(count(TeamPermission::cases()));

    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($team->id);

    try {
        foreach (TeamRole::cases() as $role) {
            $identity = FarmwellTestingUsersSeeder::Users[$role->value];
            $user = User::query()->where('email', $identity['email'])->sole();
            $user->unsetRelation('roles')->unsetRelation('permissions');

            expect($user->name)->toBe($identity['name'])
                ->and(Hash::check(FarmwellTestingUsersSeeder::Password, $user->password))->toBeTrue()
                ->and($user->email_verified_at)->not->toBeNull()
                ->and($user->current_team_id)->toBe($team->id)
                ->and($user->teamRole($team))->toBe($role)
                ->and($user->hasRole($role->value))->toBeTrue()
                ->and($user->getAllPermissions()->pluck('name')->all())->toEqualCanonicalizing(
                    array_map(
                        fn (TeamPermission $permission): string => $permission->value,
                        $role->permissions(),
                    ),
                );
        }
    } finally {
        setPermissionsTeamId($previousTeamId);
    }
});

test('testing users seeder does not create predictable credentials in production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    try {
        app(FarmwellTestingUsersSeeder::class)->run();

        expect(User::query()->count())->toBe(0)
            ->and(Team::query()->count())->toBe(0)
            ->and(Role::query()->count())->toBe(0)
            ->and(Permission::query()->count())->toBe(0);
    } finally {
        $this->app->detectEnvironment(fn () => 'testing');
    }
});
