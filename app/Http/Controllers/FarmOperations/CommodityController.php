<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\CreateCommodity;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreCommodityRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CommodityController extends Controller
{
    /**
     * Store a newly created commodity.
     */
    public function store(StoreCommodityRequest $request, Team $currentTeam, CreateCommodity $createCommodity): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $createCommodity->handle($currentTeam, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Commodity created.')]);

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }
}
