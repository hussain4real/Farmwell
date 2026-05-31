<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\CreateFarmTask;
use App\Actions\FarmOperations\UpdateFarmTaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreFarmTaskRequest;
use App\Http\Requests\FarmOperations\UpdateFarmTaskStatusRequest;
use App\Models\FarmTask;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FarmTaskController extends Controller
{
    /**
     * Store a newly scheduled farm task.
     */
    public function store(StoreFarmTaskRequest $request, Team $currentTeam, CreateFarmTask $createFarmTask): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $createFarmTask->handle($currentTeam, $user, $request->taskAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task scheduled.')]);

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }

    /**
     * Update a task status.
     */
    public function updateStatus(
        UpdateFarmTaskStatusRequest $request,
        Team $currentTeam,
        FarmTask $farmTask,
        UpdateFarmTaskStatus $updateFarmTaskStatus,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);
        assert($request->team()->is($currentTeam));
        assert($request->farmTask()->is($farmTask));

        $updateFarmTaskStatus->handle(
            team: $currentTeam,
            task: $farmTask,
            actor: $user,
            status: $request->status(),
            reason: $request->reason(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task status updated.')]);

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }
}
