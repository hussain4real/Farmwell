<?php

namespace App\Http\Controllers\Investors;

use App\Actions\Investors\BuildInvestorPageData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvestorPortfolioController extends Controller
{
    public function __invoke(Request $request, BuildInvestorPageData $pageData): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('investor-portal/Portfolio', $pageData->portfolio($user));
    }
}
