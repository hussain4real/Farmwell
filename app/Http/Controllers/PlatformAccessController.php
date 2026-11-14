<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditEvent;
use App\Http\Requests\PlatformAccessRequest;
use App\Models\Team;
use App\Support\PlatformAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlatformAccessController extends Controller
{
    public function store(PlatformAccessRequest $request, RecordAuditEvent $audit): RedirectResponse
    {
        $team = Team::findOrFail($request->validated('team_id'));
        $context = [
            'id' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'team_id' => $team->id,
            'team_name' => $team->name, 'team_slug' => $team->slug,
            'reason' => $request->validated('reason'), 'expires_at' => now()->addMinutes(30)->timestamp,
        ];
        $audit->handle($team, $request->user(), 'platform.workspace_entered', $team, reason: $context['reason'], metadata: [
            'access_id' => $context['id'], 'expires_at' => $context['expires_at'],
            'replaces_access_id' => PlatformAccess::context($request)['id'] ?? null,
        ]);
        $request->session()->put(PlatformAccess::SessionKey, $context);

        return to_route('dashboard', $team);
    }

    public function destroy(Request $request, RecordAuditEvent $audit): RedirectResponse
    {
        $context = PlatformAccess::context($request);
        if ($context) {
            $team = Team::find($context['team_id']);
            $audit->handle($team, $request->user(), 'platform.workspace_exited', $team, reason: $context['reason'], metadata: ['access_id' => $context['id']]);
        }
        $request->session()->forget(PlatformAccess::SessionKey);

        return to_route('platform.index');
    }
}
