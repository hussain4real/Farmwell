<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditEvent;
use App\Http\Requests\StartPlatformImpersonationRequest;
use App\Models\User;
use App\Support\PlatformImpersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformImpersonationController extends Controller
{
    public function store(
        StartPlatformImpersonationRequest $request,
        PlatformImpersonation $impersonation,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        /** @var User $impersonator */
        $impersonator = $request->user();
        $target = User::findOrFail($request->validated('user_id'));

        return $impersonation->start(
            $request,
            $impersonator,
            $target,
            (string) $request->validated('reason'),
            $audit,
        );
    }

    public function destroy(
        Request $request,
        PlatformImpersonation $impersonation,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $context = PlatformImpersonation::context($request);
        abort_unless($context !== null, 403, 'No active impersonation session.');

        $impersonator = User::findOrFail($context['impersonator_id']);

        return $impersonation->stop($request, $impersonator, $context, $audit, 'manual');
    }
}
