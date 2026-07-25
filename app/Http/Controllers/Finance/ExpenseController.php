<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\BuildFinancePageData;
use App\Actions\Finance\RecordExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request, Team $currentTeam, BuildFinancePageData $pageData): Response
    {
        Gate::authorize('viewFinance', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('finance/Expenses', $pageData->expenses($currentTeam, $user));
    }

    public function store(StoreExpenseRequest $request, Team $currentTeam, RecordExpense $recordExpense): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $recordExpense->handle(
            team: $currentTeam,
            actor: $user,
            attributes: $request->expenseAttributes(),
            receipts: $request->receiptFiles(),
            caption: $request->receiptCaption(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense recorded.')]);

        return to_route('finance.expenses.index', ['current_team' => $currentTeam]);
    }
}
