<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Teams\ResolveWorkspacePlan;
use App\Actions\Teams\SyncTeamRolePermissions;
use App\Enums\FarmType;
use App\Enums\TeamRole;
use App\Enums\WorkspacePlan;
use App\Http\Requests\DeletePlatformFarmRequest;
use App\Http\Requests\PlatformFarmRequest;
use App\Http\Requests\PlatformFarmStatusRequest;
use App\Http\Requests\PlatformMembershipRequest;
use App\Http\Requests\PlatformUserRequest;
use App\Http\Requests\PlatformWorkspacePlanRequest;
use App\Models\AuditEvent;
use App\Models\Farm;
use App\Models\ReportArtifact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAdminController extends Controller
{
    public function index(Request $request, ResolveWorkspacePlan $plans): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $filters['search'] ?? '';
        $planSettings = static fn ($query) => $query
            ->where('setting_group', ResolveWorkspacePlan::SettingGroup)
            ->where('setting_key', ResolveWorkspacePlan::SettingKey);

        return Inertia::render('platform/Index', [
            'search' => $search,
            'counts' => ['farms' => Farm::whereHas('team')->count(), 'users' => User::count(), 'teams' => Team::count()],
            'farms' => Farm::query()
                ->with(['team' => static fn ($query) => $query->select(['id', 'name', 'slug'])->with(['settings' => $planSettings])])
                ->whereHas('team')
                ->withCount(['productionUnits', 'productionCycles', 'investorAgreements'])
                ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                ->orderBy('name')
                ->paginate(15, ['*'], 'farms_page')
                ->withQueryString()
                ->through(function (Farm $farm) use ($plans): array {
                    $team = $farm->team;
                    assert($team instanceof Team);
                    $plan = $plans->handle($team);

                    return [
                        ...$farm->toArray(),
                        'team' => [
                            'id' => $team->id,
                            'name' => $team->name,
                            'slug' => $team->slug,
                            'plan' => $plan->value,
                            'plan_label' => $plan->label(),
                        ],
                    ];
                }),
            'users' => User::query()->select(['id', 'name', 'email', 'platform_role'])
                ->with('teams:id,name,slug')
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
                ->orderBy('name')->paginate(15, ['*'], 'users_page')->withQueryString(),
            'workspaces' => Team::query()
                ->with(['settings' => $planSettings])
                ->withCount(['farms', 'memberships'])
                ->orderBy('name')
                ->get()
                ->map(function (Team $team) use ($plans): array {
                    $plan = $plans->handle($team);

                    return [
                        'id' => $team->id,
                        'name' => $team->name,
                        'slug' => $team->slug,
                        'is_personal' => $team->is_personal,
                        'farms_count' => $team->farms_count,
                        'memberships_count' => $team->memberships_count,
                        'plan' => $plan->value,
                        'plan_label' => $plan->label(),
                    ];
                }),
            'plans' => WorkspacePlan::options(),
            'roles' => TeamRole::assignable(),
            'farmTypes' => array_map(fn (FarmType $type) => ['value' => $type->value, 'label' => $type->label()], FarmType::cases()),
            'auditEvents' => AuditEvent::query()->where('action', 'like', 'platform.%')
                ->with(['actor:id,name', 'team:id,name'])->latest('id')->limit(20)
                ->get(['id', 'team_id', 'actor_id', 'action', 'reason', 'created_at']),
        ]);
    }

    public function storeFarm(PlatformFarmRequest $request, RecordAuditEvent $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit) {
            $team = Team::findOrFail($request->validated('team_id'));
            $farm = Farm::create([...$request->safe()->except(['reason']), 'status' => 'active']);
            $audit->handle($team, $request->user(), 'platform.farm_created', $farm, newValues: $farm->only(['name', 'farm_type', 'location']), reason: $request->validated('reason'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Farm created.']);

        return back();
    }

    public function updateFarm(PlatformFarmRequest $request, Farm $farm, RecordAuditEvent $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $farm, $audit) {
            $farm = Farm::with('team')->lockForUpdate()->findOrFail($farm->id);
            abort_unless($farm->team, 404);
            $fields = $request->safe()->except(['reason']);
            $before = $farm->only(array_keys($fields));
            $farm->update($fields);
            $audit->handle($farm->team, $request->user(), 'platform.farm_updated', $farm, $before, $farm->only(array_keys($fields)), $request->validated('reason'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Farm updated.']);

        return back();
    }

    public function updateFarmStatus(PlatformFarmStatusRequest $request, Farm $farm, RecordAuditEvent $audit): RedirectResponse
    {
        $status = (string) $request->validated('status');

        DB::transaction(function () use ($request, $farm, $audit, $status): void {
            $farm = Farm::with('team')->lockForUpdate()->findOrFail($farm->id);
            abort_unless($farm->team, 404);
            $before = ['status' => $farm->status];
            $farm->update(['status' => $status]);
            $audit->handle(
                $farm->team,
                $request->user(),
                'platform.farm_status_updated',
                $farm,
                $before,
                ['status' => $farm->status],
                $request->validated('reason'),
            );
        });

        $message = match ($status) {
            'inactive' => 'Farm deactivated.',
            'archived' => 'Farm archived.',
            default => 'Farm activated.',
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function destroyFarm(DeletePlatformFarmRequest $request, Farm $farm, RecordAuditEvent $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $farm, $audit): void {
            $farm = Farm::with('team')->lockForUpdate()->findOrFail($farm->id);
            abort_unless($farm->team, 404);
            $oldValues = [
                'name' => $farm->name,
                'farm_type' => $farm->farm_type->value,
                'location' => $farm->location,
                'status' => $farm->status,
            ];
            $metadata = [
                'production_units' => $farm->productionUnits()->count(),
                'production_cycles' => $farm->productionCycles()->count(),
                'investor_agreements' => $farm->investorAgreements()->count(),
                'report_artifacts' => ReportArtifact::query()->where('farm_id', $farm->id)->count(),
            ];
            $audit->handle(
                $farm->team,
                $request->user(),
                'platform.farm_deleted',
                $farm,
                $oldValues,
                reason: $request->validated('reason'),
                metadata: $metadata,
            );
            ReportArtifact::query()->where('farm_id', $farm->id)->delete();
            $farm->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Farm deleted.']);

        return back();
    }

    public function updateUser(PlatformUserRequest $request, User $user, RecordAuditEvent $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $audit) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $before = $user->only('name');
            $user->update($request->safe()->only('name'));
            $audit->handle(null, $request->user(), 'platform.user_updated', $user, $before, $user->only('name'), $request->validated('reason'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User updated.']);

        return back();
    }

    public function updateMembership(PlatformMembershipRequest $request, User $user, SyncTeamRolePermissions $permissions, RecordAuditEvent $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $permissions, $audit) {
            $team = Team::lockForUpdate()->findOrFail($request->validated('team_id'));
            $member = $team->memberships()->where('user_id', $user->id)->lockForUpdate()->first();
            abort_if($member?->role === TeamRole::Owner, 403, 'The workspace owner cannot be changed here.');
            $role = $request->validated('role');
            $before = ['role' => $member?->role->value];
            if ($role === 'remove') {
                abort_unless($member, 404);
                $permissions->removeMembership($user, $team);
                $member->delete();
                if ($user->isCurrentTeam($team)) {
                    $user->switchAfterLeaving($team);
                }
            } else {
                $team->members()->syncWithoutDetaching([$user->id => ['role' => $role]]);
                $permissions->syncMembership($user, $team, TeamRole::from($role));
            }
            $audit->handle($team, $request->user(), 'platform.membership_updated', $user, $before, ['role' => $role], $request->validated('reason'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Workspace access updated.']);

        return back();
    }

    public function updateWorkspacePlan(
        PlatformWorkspacePlanRequest $request,
        Team $team,
        ResolveWorkspacePlan $plans,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $team, $plans, $audit): void {
            $lockedTeam = Team::lockForUpdate()->findOrFail($team->id);
            $before = ['plan' => $plans->handle($lockedTeam)->value];
            $newPlan = WorkspacePlan::from((string) $request->validated('plan'));

            $plans->setPlan($lockedTeam, $newPlan, $request->user());

            $audit->handle(
                team: $lockedTeam,
                actor: $request->user(),
                action: 'platform.workspace_plan_updated',
                subject: $lockedTeam,
                oldValues: $before,
                newValues: ['plan' => $newPlan->value],
                reason: $request->validated('reason'),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Workspace plan updated.']);

        return back();
    }
}
