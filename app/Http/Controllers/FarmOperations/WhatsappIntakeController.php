<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\ConvertWhatsappIntake;
use App\Actions\FarmOperations\CreateWhatsappIntake;
use App\Actions\FarmOperations\RejectWhatsappIntake;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\ConvertWhatsappIntakeRequest;
use App\Http\Requests\FarmOperations\RejectWhatsappIntakeRequest;
use App\Http\Requests\FarmOperations\StoreWhatsappIntakeRequest;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WhatsappIntakeController extends Controller
{
    /**
     * Store a pending WhatsApp intake record.
     */
    public function store(StoreWhatsappIntakeRequest $request, Team $currentTeam, CreateWhatsappIntake $createWhatsappIntake): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $createWhatsappIntake->handle(
            team: $currentTeam,
            actor: $user,
            attributes: $request->intakeAttributes(),
            evidence: $request->evidenceFiles(),
            evidenceCaption: $request->evidenceCaption(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('WhatsApp intake queued for review.')]);

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }

    /**
     * Convert pending WhatsApp intake into an official activity.
     */
    public function convert(
        ConvertWhatsappIntakeRequest $request,
        Team $currentTeam,
        WhatsappIntake $whatsappIntake,
        ConvertWhatsappIntake $convertWhatsappIntake,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);
        assert($request->whatsappIntake()->is($whatsappIntake));

        $convertWhatsappIntake->handle(
            team: $currentTeam,
            intake: $whatsappIntake,
            reviewer: $user,
            activityAttributes: $request->activityAttributes(),
            normalizedAttributes: $request->normalizedAttributes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('WhatsApp intake converted.')]);

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }

    /**
     * Reject pending WhatsApp intake.
     */
    public function reject(
        RejectWhatsappIntakeRequest $request,
        Team $currentTeam,
        WhatsappIntake $whatsappIntake,
        RejectWhatsappIntake $rejectWhatsappIntake,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);
        assert($request->team()->is($currentTeam));
        assert($request->whatsappIntake()->is($whatsappIntake));

        $rejectWhatsappIntake->handle(
            team: $currentTeam,
            intake: $whatsappIntake,
            reviewer: $user,
            reason: $request->rejectionReason(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('WhatsApp intake rejected.')]);

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }
}
