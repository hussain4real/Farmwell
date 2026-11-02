<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FarmwellTestingUsersSeeder extends Seeder
{
    use WithoutModelEvents;

    public const string Password = 'password';

    public const string TeamName = 'Farmwell Role Testing';

    public const string TeamSlug = 'farmwell-role-testing';

    /**
     * @var array<string, array{name: string, email: string}>
     */
    public const array Users = [
        'owner' => [
            'name' => 'Farmwell Owner',
            'email' => 'owner@farmwell.test',
        ],
        'admin' => [
            'name' => 'Farmwell Admin',
            'email' => 'admin@farmwell.test',
        ],
        'member' => [
            'name' => 'Farmwell Member',
            'email' => 'member@farmwell.test',
        ],
        'investor' => [
            'name' => 'Farmwell Investor',
            'email' => 'investor@farmwell.test',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $team = Team::query()
            ->withTrashed()
            ->firstOrNew(['slug' => self::TeamSlug]);

        $team->forceFill([
            'name' => self::TeamName,
            'slug' => self::TeamSlug,
            'is_personal' => false,
            'deleted_at' => null,
        ])->save();

        foreach (TeamRole::cases() as $role) {
            $identity = self::Users[$role->value];
            $user = User::query()->firstOrNew(['email' => $identity['email']]);

            $user->forceFill([
                'name' => $identity['name'],
                'email' => $identity['email'],
                'email_verified_at' => now(),
                'password' => self::Password,
                'current_team_id' => $team->id,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            Membership::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $role,
                ],
            );
        }

        $this->call(FarmwellBaselineSeeder::class);
    }
}
