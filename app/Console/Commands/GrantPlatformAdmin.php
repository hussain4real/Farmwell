<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrantPlatformAdmin extends Command
{
    protected $signature = 'farmwell:platform-admin {email} {--revoke}';

    protected $description = 'Grant or revoke platform administration for an existing verified account';

    public function handle(RecordAuditEvent $audit): int
    {
        $result = DB::transaction(function () use ($audit): int {
            $user = User::where('email', $this->argument('email'))->lockForUpdate()->first();
            if (! $user || (! $this->option('revoke') && ! $user->hasVerifiedEmail())) {
                $this->error('An existing verified email account is required.');

                return self::FAILURE;
            }
            $before = $user->platform_role?->value;
            $user->platform_role = $this->option('revoke') ? null : PlatformRole::SuperAdmin;
            $user->save();
            $audit->handle(null, null, 'platform.role_changed', $user, ['platform_role' => $before], ['platform_role' => $user->platform_role?->value], 'Authorized CLI administration');

            return self::SUCCESS;
        });
        if ($result !== self::SUCCESS) {
            return $result;
        }
        $this->info('Platform role updated. Farm memberships were not changed.');

        return self::SUCCESS;
    }
}
