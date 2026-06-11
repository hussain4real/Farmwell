<?php

namespace Database\Seeders;

use App\Actions\Finance\EnsureDefaultExpenseCategories;
use App\Actions\Investors\ResolveInvestorApprovalSettings;
use App\Actions\Teams\SyncTeamRolePermissions;
use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalTriggerType;
use App\Models\ApprovalRule;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FarmwellBaselineSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(
        SyncTeamRolePermissions $syncTeamRolePermissions,
        EnsureDefaultExpenseCategories $ensureDefaultExpenseCategories,
        ResolveInvestorApprovalSettings $resolveInvestorApprovalSettings,
    ): void {
        $syncTeamRolePermissions->ensureBaseline();

        Membership::query()
            ->with(['team', 'user'])
            ->orderBy('id')
            ->each(function (Membership $membership) use ($syncTeamRolePermissions): void {
                $syncTeamRolePermissions->syncMembership($membership->user, $membership->team, $membership->role);
            });

        Team::query()
            ->orderBy('id')
            ->each(function (Team $team) use ($ensureDefaultExpenseCategories, $resolveInvestorApprovalSettings): void {
                $this->seedTeamSettings($team);
                $ensureDefaultExpenseCategories->handle($team);
                $this->seedApprovalThresholdRule($team, $resolveInvestorApprovalSettings);
            });
    }

    private function seedTeamSettings(Team $team): void
    {
        TeamSetting::query()->firstOrCreate(
            [
                'team_id' => $team->id,
                'setting_group' => 'finance',
                'setting_key' => 'default_currency',
            ],
            [
                'value' => ['currency' => ResolveInvestorApprovalSettings::DefaultCurrency],
                'updated_by' => null,
            ],
        );

        TeamSetting::query()->firstOrCreate(
            [
                'team_id' => $team->id,
                'setting_group' => 'approvals',
                'setting_key' => 'investor_expense_threshold',
            ],
            [
                'value' => [
                    'currency' => ResolveInvestorApprovalSettings::DefaultCurrency,
                    'threshold_amount_minor' => ResolveInvestorApprovalSettings::DefaultExpenseThresholdMinor,
                ],
                'updated_by' => null,
            ],
        );
    }

    private function seedApprovalThresholdRule(Team $team, ResolveInvestorApprovalSettings $resolveInvestorApprovalSettings): void
    {
        $settings = $resolveInvestorApprovalSettings->handle($team);

        ApprovalRule::query()->firstOrCreate(
            [
                'team_id' => $team->id,
                'request_type' => ApprovalRequestType::Expense->value,
                'trigger_type' => ApprovalTriggerType::Threshold->value,
                'investor_agreement_id' => null,
                'expense_category_id' => null,
                'funding_phase_id' => null,
            ],
            [
                'created_by_id' => null,
                'name' => 'Default investor expense threshold',
                'threshold_amount_minor' => $settings['expenseThresholdMinor'],
                'currency' => $settings['currency'],
                'farm_type' => null,
                'is_active' => true,
                'notes' => 'Seeded baseline approval threshold.',
            ],
        );
    }
}
