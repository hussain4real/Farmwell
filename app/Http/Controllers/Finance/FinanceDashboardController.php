<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\BuildFinancePageData;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinanceDashboardController extends Controller
{
    public function __invoke(Request $request, Team $currentTeam, BuildFinancePageData $pageData): Response
    {
        Gate::authorize('viewFinance', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('finance/Index', $pageData->dashboard($currentTeam, $user));
    }
}
