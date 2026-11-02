<?php

namespace Database\Seeders;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Enums\DistributionStatus;
use App\Enums\ExpenseStatus;
use App\Enums\FarmTaskStatus;
use App\Enums\FarmType;
use App\Enums\FundingPhaseStatus;
use App\Enums\HarvestRecordStatus;
use App\Enums\HarvestStage;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionUnitType;
use App\Models\AiSummary;
use App\Models\ApprovalRequest;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Commodity;
use App\Models\DistributionRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExternalTransfer;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\FundingPhase;
use App\Models\HarvestRecord;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FarmwellDemoDataSeeder extends Seeder
{
    public const string FarmName = 'DEMO — Greenfield Farm';

    public function run(ResolveTeamFinanceCurrency $resolveCurrency): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $team = Team::query()->where('slug', FarmwellTestingUsersSeeder::TeamSlug)->firstOrFail();
        $owner = User::query()->where('email', FarmwellTestingUsersSeeder::Users['owner']['email'])->firstOrFail();
        $investor = User::query()->where('email', FarmwellTestingUsersSeeder::Users['investor']['email'])->firstOrFail();

        DB::transaction(function () use ($team, $owner, $investor, $resolveCurrency): void {
            if ($team->farms()->where('name', self::FarmName)->exists()) {
                return;
            }

            $currency = $resolveCurrency->handle($team);
            $farm = Farm::factory()->create(['team_id' => $team->id, 'name' => self::FarmName, 'farm_type' => FarmType::Crop, 'location' => 'Demo location', 'contact_name' => null, 'contact_phone' => null, 'registration_number' => null, 'description' => 'Fictional demo data.']);
            $unit = ProductionUnit::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'name' => 'DEMO — North field', 'unit_type' => ProductionUnitType::Field, 'size' => 2, 'size_unit' => 'acres', 'gps_coordinates' => null, 'capacity' => null, 'suitability_notes' => null, 'history_notes' => null]);
            $commodity = Commodity::factory()->create(['team_id' => $team->id, 'name' => 'DEMO — Maize', 'farm_type' => FarmType::Crop, 'measurement_unit' => 'kg', 'notes' => 'Fictional demo data.']);
            $cycle = ProductionCycle::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'name' => 'DEMO — Maize season', 'season' => (string) now()->year, 'farm_type' => FarmType::Crop, 'status' => ProductionCycleStatus::Active, 'planned_start_on' => now()->subMonths(2), 'planned_end_on' => now()->addMonths(2), 'plan_summary' => 'Fictional demonstration season.']);
            $cycle->productionUnits()->attach($unit);
            $cycle->commodities()->attach($commodity, ['role' => 'primary', 'expected_output_quantity' => 1000, 'expected_output_unit' => 'kg']);
            $agreement = InvestorAgreement::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'investor_id' => $investor->id, 'created_by_id' => $owner->id, 'title' => 'DEMO — Greenfield investor agreement', 'currency' => $currency, 'amount_committed_minor' => 1_000_000_00, 'amount_funded_minor' => 600_000_00, 'public_notes' => 'Fictional demo agreement; no actual investment.', 'internal_notes' => null, 'role_responsibilities' => null, 'funding_model' => 'Demo only']);
            $activity = FarmActivity::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'production_unit_id' => $unit->id, 'commodity_id' => $commodity->id, 'recorded_by_id' => $owner->id, 'activity_date' => now()->subDays(2), 'activity_type' => 'DEMO — Planting', 'description' => 'Fictional example: maize planting completed.', 'investor_safe_summary' => 'Demo only: maize planting completed on the north field.', 'internal_notes' => null, 'inputs_used' => null, 'labour_used' => null, 'remarks' => null, 'cost' => null, 'next_activity' => 'Check germination']);
            FarmTask::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'production_unit_id' => $unit->id, 'created_by_id' => $owner->id, 'title' => 'DEMO — Check germination', 'activity_type' => 'Inspection', 'description' => 'Fictional follow-up task.', 'status' => FarmTaskStatus::Planned, 'due_on' => now()->addDays(3), 'investor_visible' => true]);
            WhatsappIntake::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'production_unit_id' => $unit->id, 'imported_by_id' => $owner->id, 'source_message' => 'DEMO: The team watered the north field this morning.', 'source_sender' => null, 'source_date' => now()]);
            $category = ExpenseCategory::query()->where('team_id', $team->id)->orderBy('id')->firstOrFail();
            $budget = Budget::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'created_by_id' => $owner->id, 'name' => 'DEMO — Season budget', 'currency' => $currency, 'notes' => 'Fictional demo budget.']);
            $line = BudgetLine::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'budget_id' => $budget->id, 'expense_category_id' => $category->id, 'description' => 'DEMO — Farm inputs', 'planned_amount_minor' => 300_000_00, 'currency' => $currency]);
            $phase = FundingPhase::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'budget_id' => $budget->id, 'investor_agreement_id' => $agreement->id, 'created_by_id' => $owner->id, 'name' => 'DEMO — First funding phase', 'milestone' => 'Demo planting milestone', 'currency' => $currency, 'status' => FundingPhaseStatus::ReleasedExternally, 'investor_visibility_status' => InvestorVisibilityStatus::Approved, 'planned_amount_minor' => 600_000_00, 'requested_amount_minor' => 600_000_00, 'approved_amount_minor' => 600_000_00, 'externally_released_amount_minor' => 600_000_00, 'expected_on' => now()->subDays(10), 'released_on' => now()->subDays(10), 'notes' => 'Fictional demo release.']);
            Expense::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'budget_id' => $budget->id, 'budget_line_id' => $line->id, 'funding_phase_id' => $phase->id, 'investor_agreement_id' => $agreement->id, 'expense_category_id' => $category->id, 'farm_activity_id' => $activity->id, 'recorded_by_id' => $owner->id, 'incurred_on' => now()->subDays(2), 'vendor' => 'Demo supplier', 'description' => 'DEMO — Farm inputs', 'amount_minor' => 120_000_00, 'currency' => $currency, 'investor_visibility_status' => InvestorVisibilityStatus::Approved, 'notes' => null]);
            ExternalTransfer::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'budget_id' => $budget->id, 'funding_phase_id' => $phase->id, 'investor_agreement_id' => $agreement->id, 'recorded_by_id' => $owner->id, 'counterparty_name' => 'DEMO — Investor', 'reference' => 'DEMO-TRANSFER-001', 'amount_minor' => 600_000_00, 'currency' => $currency, 'transferred_on' => now()->subDays(10), 'investor_visibility_status' => InvestorVisibilityStatus::Approved, 'notes' => 'Fictional external transfer.']);
            $harvest = HarvestRecord::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'production_unit_id' => $unit->id, 'commodity_id' => $commodity->id, 'investor_agreement_id' => $agreement->id, 'recorded_by_id' => $owner->id, 'harvested_on' => now()->subDay(), 'stage' => HarvestStage::Single, 'quantity' => 100, 'quantity_unit' => 'kg', 'quality_notes' => 'Fictional example.', 'labour_cost_minor' => 0, 'currency' => $currency, 'status' => HarvestRecordStatus::PartiallySold, 'investor_visibility_status' => InvestorVisibilityStatus::Approved, 'notes' => null]);
            $sale = SaleRecord::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'harvest_record_id' => $harvest->id, 'commodity_id' => $commodity->id, 'investor_agreement_id' => $agreement->id, 'recorded_by_id' => $owner->id, 'sold_on' => now(), 'buyer_name' => 'DEMO — Local buyer', 'quantity' => 50, 'quantity_unit' => 'kg', 'unit_price_minor' => 2_000_00, 'gross_amount_minor' => 100_000_00, 'deduction_amount_minor' => 5_000_00, 'net_amount_minor' => 95_000_00, 'currency' => $currency, 'investor_visibility_status' => InvestorVisibilityStatus::Approved, 'notes' => null]);
            DistributionRecord::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'investor_agreement_id' => $agreement->id, 'sale_record_id' => $sale->id, 'sale_gross_amount_minor' => 100_000_00, 'sale_net_amount_minor' => 95_000_00, 'previous_capital_recovered_minor' => 0, 'capital_recovered_minor' => 95_000_00, 'unrecovered_capital_minor' => 505_000_00, 'gross_profit_minor' => 0, 'net_profit_minor' => 0, 'investor_share_minor' => 0, 'farm_share_minor' => 0, 'currency' => $currency, 'status' => DistributionStatus::Acknowledged, 'investor_visibility_status' => InvestorVisibilityStatus::Approved, 'acknowledged_at' => now(), 'notes' => 'Fictional demo calculation.']);
            $pendingExpense = Expense::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'budget_id' => $budget->id, 'budget_line_id' => $line->id, 'funding_phase_id' => $phase->id, 'investor_agreement_id' => $agreement->id, 'expense_category_id' => $category->id, 'farm_activity_id' => null, 'recorded_by_id' => $owner->id, 'incurred_on' => now(), 'vendor' => 'Demo supplier', 'description' => 'DEMO — Irrigation supplies for review', 'amount_minor' => 45_000_00, 'currency' => $currency, 'status' => ExpenseStatus::Submitted, 'investor_visibility_status' => InvestorVisibilityStatus::PendingApproval, 'notes' => null]);
            ApprovalRequest::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id, 'investor_agreement_id' => $agreement->id, 'requested_by_id' => $owner->id, 'subject_type' => $pendingExpense->getMorphClass(), 'subject_id' => $pendingExpense->id, 'currency' => $currency, 'requested_amount_minor' => 45_000_00, 'requester_comment' => 'DEMO — Review of irrigation supplies.']);
            AiSummary::factory()->create(['team_id' => $team->id, 'investor_agreement_id' => $agreement->id, 'requested_by_id' => $owner->id, 'type' => 'investor_progress', 'status' => 'published', 'generated_text' => 'DEMO: Maize planting is complete.', 'final_text' => '**Demo progress:** Maize planting is complete on the north field.', 'reviewed_by_id' => $owner->id, 'reviewed_at' => now(), 'published_by_id' => $owner->id, 'published_at' => now(), 'source_activity_ids' => [$activity->id], 'source_snapshot' => [['id' => $activity->id, 'date' => $activity->activity_date->toDateString(), 'type' => $activity->activity_type, 'status' => $activity->status->value, 'summary' => $activity->investor_safe_summary]], 'generation_context' => ['team_id' => $team->id, 'investor_id' => $investor->id, 'farm_id' => $farm->id, 'production_cycle_id' => $cycle->id]]);
        });
    }
}
