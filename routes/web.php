<?php

use App\Http\Controllers\FarmOperations\CommodityController;
use App\Http\Controllers\FarmOperations\EvidenceController;
use App\Http\Controllers\FarmOperations\FarmActivityController;
use App\Http\Controllers\FarmOperations\FarmController;
use App\Http\Controllers\FarmOperations\FarmDashboardController;
use App\Http\Controllers\FarmOperations\FarmOperationsController;
use App\Http\Controllers\FarmOperations\FarmTaskCalendarController;
use App\Http\Controllers\FarmOperations\FarmTaskController;
use App\Http\Controllers\FarmOperations\FieldDiaryController;
use App\Http\Controllers\FarmOperations\ProductionCycleController;
use App\Http\Controllers\FarmOperations\ProductionPlanChangeController;
use App\Http\Controllers\FarmOperations\ProductionUnitController;
use App\Http\Controllers\FarmOperations\WhatsappIntakeController;
use App\Http\Controllers\FarmOperations\WhatsappIntakeReviewController;
use App\Http\Controllers\Finance\BudgetController;
use App\Http\Controllers\Finance\BudgetLineController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\ExternalTransferController;
use App\Http\Controllers\Finance\FinanceDashboardController;
use App\Http\Controllers\Finance\FinanceEvidenceController;
use App\Http\Controllers\Finance\FinanceSettingController;
use App\Http\Controllers\Finance\FundingPhaseController;
use App\Http\Controllers\Finance\TransferReconciliationController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::get('dashboard', FarmDashboardController::class)->name('dashboard');
        Route::get('farms', FarmOperationsController::class)->name('farms.index');
        Route::get('field-diary', FieldDiaryController::class)->name('field-diary.index');
        Route::get('farm-tasks', FarmTaskCalendarController::class)->name('farm-tasks.index');
        Route::get('whatsapp-intakes', WhatsappIntakeReviewController::class)->name('whatsapp-intakes.index');
        Route::get('finance', FinanceDashboardController::class)->name('finance.index');
        Route::get('finance/budgets', [BudgetController::class, 'index'])->name('finance.budgets.index');
        Route::get('finance/expenses', [ExpenseController::class, 'index'])->name('finance.expenses.index');
        Route::get('finance/funding-phases', [FundingPhaseController::class, 'index'])->name('finance.funding-phases.index');
        Route::get('finance/external-transfers', [ExternalTransferController::class, 'index'])->name('finance.external-transfers.index');
        Route::post('commodities', [CommodityController::class, 'store'])->name('commodities.store');
        Route::post('farms', [FarmController::class, 'store'])->name('farms.store');
        Route::post('farms/{farm}/production-units', [ProductionUnitController::class, 'store'])->name('farms.production-units.store');
        Route::post('farms/{farm}/production-cycles', [ProductionCycleController::class, 'store'])->name('farms.production-cycles.store');
        Route::post('farms/{farm}/production-cycles/{production_cycle}/plan-changes', [ProductionPlanChangeController::class, 'store'])
            ->name('farms.production-cycles.plan-changes.store');
        Route::post('farm-activities', [FarmActivityController::class, 'store'])->name('farm-activities.store');
        Route::post('farm-tasks', [FarmTaskController::class, 'store'])->name('farm-tasks.store');
        Route::patch('farm-tasks/{farm_task}/status', [FarmTaskController::class, 'updateStatus'])->name('farm-tasks.status.update');
        Route::post('whatsapp-intakes', [WhatsappIntakeController::class, 'store'])->name('whatsapp-intakes.store');
        Route::patch('whatsapp-intakes/{whatsapp_intake}/convert', [WhatsappIntakeController::class, 'convert'])->name('whatsapp-intakes.convert');
        Route::patch('whatsapp-intakes/{whatsapp_intake}/reject', [WhatsappIntakeController::class, 'reject'])->name('whatsapp-intakes.reject');
        Route::patch('finance/settings/currency', [FinanceSettingController::class, 'updateCurrency'])->name('finance.settings.currency.update');
        Route::post('finance/budgets', [BudgetController::class, 'store'])->name('finance.budgets.store');
        Route::post('finance/budgets/{budget}/lines', [BudgetLineController::class, 'store'])->name('finance.budget-lines.store');
        Route::post('finance/funding-phases', [FundingPhaseController::class, 'store'])->name('finance.funding-phases.store');
        Route::post('finance/expenses', [ExpenseController::class, 'store'])->name('finance.expenses.store');
        Route::post('finance/external-transfers', [ExternalTransferController::class, 'store'])->name('finance.external-transfers.store');
        Route::post('finance/external-transfers/{external_transfer}/reconciliations', [TransferReconciliationController::class, 'store'])
            ->name('finance.external-transfers.reconciliations.store');
        Route::get('evidence/{media}', [EvidenceController::class, 'show'])
            ->withoutScopedBindings()
            ->name('farm-evidence.show');
        Route::get('finance/evidence/{media}', [FinanceEvidenceController::class, 'show'])
            ->withoutScopedBindings()
            ->name('finance.evidence.show');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
