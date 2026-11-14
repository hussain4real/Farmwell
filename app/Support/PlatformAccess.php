<?php

namespace App\Support;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\PlatformRole;
use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PlatformAccess
{
    public const string SessionKey = 'platform_access';

    private const array ReadRoutes = [
        'dashboard', 'farms.index', 'farm-tasks.index', 'whatsapp-intakes.index', 'harvests.index', 'field-diary.index', 'finance.index', 'finance.budgets.index',
        'finance.expenses.index', 'finance.funding-phases.index', 'finance.external-transfers.index',
        'investors.index', 'investors.approvals.index', 'farm-evidence.show',
        'finance.evidence.show', 'investor-evidence.show',
    ];

    public static function allows(User $user, Team $team, TeamPermission $permission): bool
    {
        return self::activeFor($user, $team) && in_array($permission, [
            TeamPermission::ViewFarmOperations, TeamPermission::ViewFinance,
            TeamPermission::ViewInvestorAgreements, TeamPermission::ViewApprovalRequests,
        ], true);
    }

    public static function activeFor(User $user, Team $team): bool
    {
        return $user->platform_role === PlatformRole::SuperAdmin
            && request()->attributes->get('platform_access_actor') === $user->id
            && request()->attributes->get('platform_access_team')?->id === $team->id;
    }

    /** @return array{id: string, user_id: int, team_id: int, team_name: string, team_slug: string, reason: string, expires_at: int}|null */
    public static function context(Request $request): ?array
    {
        $context = $request->session()->get(self::SessionKey);

        return is_array($context)
            && $request->user()?->platform_role === PlatformRole::SuperAdmin
            && $context['user_id'] === $request->user()->id
            && $context['expires_at'] > now()->timestamp ? $context : null;
    }

    public function enterRequest(Request $request, Team $team, RecordAuditEvent $audit): bool
    {
        if (! $request->session()->has(self::SessionKey)) {
            return false;
        }
        $context = self::context($request);
        if (! $context) {
            $request->session()->forget(self::SessionKey);
            abort(403, 'The admin session has expired or is no longer authorized.');
        }
        abort_unless($context['team_id'] === $team->id, 403, 'Exit the admin session before changing workspace.');
        abort_unless($request->isMethodSafe() && $request->routeIs(...self::ReadRoutes), 403, 'This admin session is read-only.');

        $audit->handle($team, $request->user(), 'platform.workspace_viewed', $team, reason: $context['reason'], metadata: [
            'access_id' => $context['id'], 'route' => $request->route()->getName(),
            'media_id' => $request->route('media')?->id,
        ]);
        $request->attributes->set('platform_access_team', $team);
        $request->attributes->set('platform_access_actor', $request->user()->id);
        URL::defaults(['current_team' => $team->slug, 'team' => $team->slug]);

        return true;
    }
}
