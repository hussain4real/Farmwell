<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\BuildFarmOperationsPageData;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappIntakeReviewController extends Controller
{
    /**
     * Show WhatsApp intake review records.
     */
    public function __invoke(Request $request, Team $currentTeam, BuildFarmOperationsPageData $pageData): Response
    {
        Gate::authorize('viewFarmOperations', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('whatsapp-intakes/Index', $pageData->whatsappIntake($currentTeam, $user));
    }
}
