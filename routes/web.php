<?php

use App\Http\Controllers\FarmOperations\CommodityController;
use App\Http\Controllers\FarmOperations\FarmController;
use App\Http\Controllers\FarmOperations\FarmDashboardController;
use App\Http\Controllers\FarmOperations\ProductionCycleController;
use App\Http\Controllers\FarmOperations\ProductionPlanChangeController;
use App\Http\Controllers\FarmOperations\ProductionUnitController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::get('dashboard', FarmDashboardController::class)->name('dashboard');
        Route::get('farms', FarmDashboardController::class)->name('farms.index');
        Route::post('commodities', [CommodityController::class, 'store'])->name('commodities.store');
        Route::post('farms', [FarmController::class, 'store'])->name('farms.store');
        Route::post('farms/{farm}/production-units', [ProductionUnitController::class, 'store'])->name('farms.production-units.store');
        Route::post('farms/{farm}/production-cycles', [ProductionCycleController::class, 'store'])->name('farms.production-cycles.store');
        Route::post('farms/{farm}/production-cycles/{production_cycle}/plan-changes', [ProductionPlanChangeController::class, 'store'])
            ->name('farms.production-cycles.plan-changes.store');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
