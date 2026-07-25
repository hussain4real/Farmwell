<?php

namespace App\Http\Controllers\Harvests;

use App\Actions\Harvests\RecordHarvest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Harvests\StoreHarvestRecordRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class HarvestRecordController extends Controller
{
    public function store(StoreHarvestRecordRequest $request, Team $currentTeam, RecordHarvest $recordHarvest): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $recordHarvest->handle(
            team: $currentTeam,
            actor: $user,
            attributes: $request->harvestAttributes(),
            evidence: $request->evidenceFiles(),
            caption: $request->evidenceCaption(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Harvest recorded.')]);

        return to_route('harvests.index', ['current_team' => $currentTeam]);
    }
}
