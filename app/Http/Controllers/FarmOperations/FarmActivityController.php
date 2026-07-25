<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\RecordFarmActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreFarmActivityRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FarmActivityController extends Controller
{
    /**
     * Store a newly recorded farm activity.
     */
    public function store(StoreFarmActivityRequest $request, Team $currentTeam, RecordFarmActivity $recordFarmActivity): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $recordFarmActivity->handle(
            team: $currentTeam,
            actor: $user,
            attributes: $request->activityAttributes(),
            evidence: $request->evidenceFiles(),
            evidenceCaption: $request->evidenceCaption(),
            evidenceVisibility: $request->evidenceVisibility(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Activity recorded.')]);

        return to_route('field-diary.index', ['current_team' => $currentTeam]);
    }
}
