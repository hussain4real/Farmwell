<?php

namespace App\Http\Controllers\Harvests;

use App\Actions\Harvests\BuildHarvestPageData;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HarvestDashboardController extends Controller
{
    public function __invoke(Request $request, Team $currentTeam, BuildHarvestPageData $pageData): Response
    {
        abort_unless(
            $request->user()?->can('viewFarmOperations', $currentTeam) === true
            || $request->user()?->can('viewFinance', $currentTeam) === true,
            403,
        );

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('harvests/Index', $pageData->dashboard($currentTeam, $user));
    }
}
