<?php

namespace App\Http\Controllers\Investors;

use App\Actions\Investors\BuildInvestorPageData;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvestorApprovalController extends Controller
{
    public function __invoke(Request $request, Team $currentTeam, BuildInvestorPageData $pageData): Response
    {
        Gate::authorize('viewApprovalRequests', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('investors/Approvals', $pageData->approvals($currentTeam, $user));
    }
}
