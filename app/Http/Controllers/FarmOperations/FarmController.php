<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\CreateFarm;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreFarmRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FarmController extends Controller
{
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
}
