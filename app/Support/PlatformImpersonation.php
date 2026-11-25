<?php

namespace App\Support;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\PlatformRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PlatformImpersonation
{
    public const string SessionKey = 'platform_impersonation';

    public const int DurationMinutes = 60;

    /**
     * Get the active platform impersonation context for the authenticated user.
     *
     * @return array{
     *     id: string,
     *     impersonator_id: int,
     *     impersonator_name: string,
     *     target_user_id: int,
     *     target_user_name: string,
     *     target_user_email: ?string,
     *     reason: string,
     *     started_at: int,
     *     expires_at: int,
     * }|null
     */
    public static function context(Request $request): ?array
    {
        $context = $request->session()->get(self::SessionKey);

        if (! is_array($context) || ($context['target_user_id'] ?? null) !== $request->user()?->id) {
            return null;
        }

        /** @var array{id: string, impersonator_id: int, impersonator_name: string, target_user_id: int, target_user_name: string, target_user_email: ?string, reason: string, started_at: int, expires_at: int} $context */
        return $context;
    }

    /**
     * Switch the active session to the target user and record an audit event.
     */
    public function start(Request $request, User $impersonator, User $target, string $reason, RecordAuditEvent $audit): RedirectResponse
    {
        abort_if(
            $target->id === $impersonator->id || $target->platform_role === PlatformRole::SuperAdmin,
            403,
            'Super Admins cannot impersonate themselves or another Super Admin.',
        );

        $now = now();
        $context = [
            'id' => (string) Str::uuid(),
            'impersonator_id' => $impersonator->id,
            'impersonator_name' => $impersonator->name,
            'target_user_id' => $target->id,
            'target_user_name' => $target->name,
            'target_user_email' => $target->email,
            'reason' => $reason,
            'started_at' => $now->timestamp,
            'expires_at' => $now->copy()->addMinutes(self::DurationMinutes)->timestamp,
        ];

        $team = $this->resolveTeam($target);

        $audit->handle(
            $team,
            $impersonator,
            'platform.impersonation_started',
            $target,
            reason: $reason,
            metadata: [
                'impersonation_id' => $context['id'],
                'impersonator_id' => $impersonator->id,
                'target_user_id' => $target->id,
                'expires_at' => $context['expires_at'],
            ],
        );

        $request->session()->forget(PlatformAccess::SessionKey);
        Auth::login($target);
        $request->session()->regenerate();
        $request->session()->put(self::SessionKey, $context);

        return $this->redirectToTargetHome($target, $team);
    }

    /**
     * End the active impersonation session and restore the original Super Admin.
     *
     * @param  array{
     *     id: string,
     *     impersonator_id: int,
     *     impersonator_name: string,
     *     target_user_id: int,
     *     target_user_name: string,
     *     target_user_email: ?string,
     *     reason: string,
     *     started_at: int,
     *     expires_at: int,
     * }  $context
     */
    public function stop(Request $request, User $impersonator, array $context, RecordAuditEvent $audit, string $endReason = 'manual'): RedirectResponse
    {
        /** @var User $target */
        $target = $request->user();

        $audit->handle(
            $this->resolveTeam($target),
            $impersonator,
            'platform.impersonation_ended',
            $target,
            reason: $context['reason'],
            metadata: [
                'impersonation_id' => $context['id'],
                'target_user_id' => $context['target_user_id'],
                'end_reason' => $endReason,
            ],
        );

        $request->session()->forget([self::SessionKey, PlatformAccess::SessionKey]);
        Auth::login($impersonator);
        $request->session()->regenerate();

        return to_route('platform.index');
    }

    protected function resolveTeam(User $target): ?Team
    {
        return $target->currentTeam ?? $target->personalTeam() ?? $target->teams()->first();
    }

    protected function redirectToTargetHome(User $target, ?Team $team): RedirectResponse
    {
        if ($team === null) {
            return to_route('teams.index');
        }

        $route = $target->can('viewInvestorPortal', $team) && $target->cannot('viewFieldWork', $team)
            ? 'investor-portal.index'
            : 'dashboard';

        return to_route($route, ['current_team' => $team]);
    }
}
