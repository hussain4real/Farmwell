<?php

namespace App\Http\Controllers\Health;

use App\Actions\Health\ApplyHealthSchedulePreset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Health\ApplyHealthPresetRequest;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class HealthPresetController extends Controller
{
    public function store(
        ApplyHealthPresetRequest $request,
        Team $currentTeam,
        Farm $farm,
        ProductionCycle $productionCycle,
        ApplyHealthSchedulePreset $applyHealthSchedulePreset,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $created = $applyHealthSchedulePreset->handle(
            $currentTeam,
            $productionCycle,
            $user,
            (string) $request->validated('preset_key'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Applied schedule preset (:count events added).', ['count' => $created->count()]),
        ]);

        return back();
    }
}
