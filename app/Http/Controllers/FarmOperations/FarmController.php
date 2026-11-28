<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\BuildFarmDetailPageData;
use App\Actions\FarmOperations\CreateFarm;
use App\Actions\FarmOperations\UpdateFarm;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreFarmRequest;
use App\Http\Requests\FarmOperations\UpdateFarmRequest;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FarmController extends Controller
{
    /**
     * Display the farm command center.
     */
    public function show(Request $request, Team $currentTeam, Farm $farm, BuildFarmDetailPageData $pageData): Response
    {
        Gate::authorize('viewFieldWork', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('farms/Show', $pageData->handle($currentTeam, $farm, $user));
    }

    /**
     * Store a newly created farm.
     */
    public function store(StoreFarmRequest $request, Team $currentTeam, CreateFarm $createFarm): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $createFarm->handle($currentTeam, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Farm created.')]);

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }

    /**
     * Update an existing farm.
     */
    public function update(UpdateFarmRequest $request, Team $currentTeam, Farm $farm, UpdateFarm $updateFarm): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $updateFarm->handle($currentTeam, $farm, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Farm updated.')]);

        return to_route('farms.show', ['current_team' => $currentTeam, 'farm' => $farm]);
    }
}
