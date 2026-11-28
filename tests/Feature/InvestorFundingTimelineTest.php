<?php

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\FundingPhaseStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FundingPhase;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('investors can follow shared funding milestones and evidence by season without seeing another agreement', function () {
    $this->withoutVite();
    Storage::fake('local');

    $team = Team::factory()->create();
    $investor = User::factory()->create();
    $otherInvestor = User::factory()->create();
    $team->members()->attach([
        $investor->id => ['role' => TeamRole::Investor],
        $otherInvestor->id => ['role' => TeamRole::Investor],
    ]);
    $farm = Farm::factory()->for($team)->create();
    $rice = ProductionCycle::factory()->for($team)->for($farm)->create(['name' => 'Rice', 'season' => 'Wet season']);
    $pepper = ProductionCycle::factory()->for($team)->for($farm)->create(['name' => 'Pepper', 'season' => 'Dry season']);
    $agreement = InvestorAgreement::factory()->for($team)->for($farm)->for($investor, 'investor')->create();
    $otherAgreement = InvestorAgreement::factory()->for($team)->for($farm)->for($otherInvestor, 'investor')->create();

    $released = FundingPhase::factory()->for($team)->for($farm)->for($agreement)->for($rice)->create([
        'name' => 'Rice planting', 'status' => FundingPhaseStatus::ReleasedExternally,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
        'externally_released_amount_minor' => 80_000, 'expected_on' => '2026-10-01', 'released_on' => '2026-10-02',
    ]);
    $request = ApprovalRequest::factory()->for($team)->for($agreement)->for($released, 'subject')->create([
        'farm_id' => $farm->id,
        'request_type' => ApprovalRequestType::FundingRelease, 'status' => ApprovalRequestStatus::Approved,
        'requested_at' => '2026-09-27 08:00:00', 'decided_at' => '2026-10-01 12:00:00',
        'requested_amount_minor' => 100_000, 'approved_amount_minor' => 90_000,
    ]);
    $evidence = $released->addMedia(UploadedFile::fake()->create('inspection.pdf', 4, 'application/pdf'))
        ->toMediaCollection(FundingPhase::EvidenceCollection, 'local');
    $approvedExpense = Expense::factory()->for($team)->for($farm)->for($agreement)->for($released)->for($rice)->create([
        'description' => 'Rice seed purchase', 'amount_minor' => 25_000, 'incurred_on' => '2026-10-03',
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    $receipt = $approvedExpense->addMedia(UploadedFile::fake()->create('seed-receipt.pdf', 5, 'application/pdf'))
        ->toMediaCollection(Expense::ReceiptsCollection, 'local');
    Expense::factory()->for($team)->for($farm)->for($agreement)->for($released)->create([
        'description' => 'Internal travel', 'investor_visibility_status' => InvestorVisibilityStatus::Private,
    ]);
    Expense::factory()->for($team)->for($farm)->for($otherAgreement)->for($released)->create([
        'description' => 'Other investor purchase', 'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);

    $pending = FundingPhase::factory()->for($team)->for($farm)->for($agreement)->for($pepper)->create([
        'name' => 'Pepper nursery', 'status' => FundingPhaseStatus::Requested, 'expected_on' => '2026-11-01',
        'investor_visibility_status' => InvestorVisibilityStatus::PendingApproval,
        'externally_released_amount_minor' => 0,
    ]);
    ApprovalRequest::factory()->for($team)->for($agreement)->for($pending, 'subject')->create([
        'request_type' => ApprovalRequestType::FundingRelease, 'requested_at' => '2026-10-28 08:00:00',
    ]);
    FundingPhase::factory()->for($team)->for($farm)->for($agreement)->for($pepper)->create(['name' => 'Private draft']);
    $unrelatedFarm = Farm::factory()->for($team)->create();
    $unrelatedCycle = ProductionCycle::factory()->for($team)->for($unrelatedFarm)->create();
    FundingPhase::factory()->for($team)->for($unrelatedFarm)->for($agreement)->create([
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    FundingPhase::factory()->for($team)->for($farm)->for($agreement)->for($unrelatedCycle)->create([
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    $otherPhase = FundingPhase::factory()->for($team)->for($farm)->for($otherAgreement)->for($pepper)->create([
        'name' => 'Other investor milestone', 'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    ApprovalRequest::factory()->for($team)->for($agreement)->for($otherPhase, 'subject')->create([
        'request_type' => ApprovalRequestType::FundingRelease,
    ]);
    $url = route('investor-portal.index', $team);

    $this->actingAs($investor)->get($url)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('agreements', 1)
            ->has('agreements.0.fundingTimeline', 2)
            ->where('agreements.0.fundingTimeline.0.cycleName', 'Rice')
            ->where('agreements.0.fundingTimeline.0.season', 'Wet season')
            ->has('agreements.0.fundingTimeline.0.milestones', 1)
            ->where('agreements.0.fundingTimeline.0.milestones.0.id', $released->id)
            ->has('agreements.0.fundingTimeline.0.milestones.0.events', 4, fn (Assert $event) => $event
                ->where('label', 'Funding requested')->where('amount', '1000.00')
                ->where('date', $request->requested_at->toISOString())
                ->where('files.0.downloadUrl', route('investor-evidence.show', [$team, $evidence]))->etc())
            ->where('agreements.0.fundingTimeline.0.milestones.0.events.1.label', 'Approved')
            ->where('agreements.0.fundingTimeline.0.milestones.0.events.1.amount', '900.00')
            ->where('agreements.0.fundingTimeline.0.milestones.0.events.2.label', 'Release recorded')
            ->where('agreements.0.fundingTimeline.0.milestones.0.events.2.amount', '800.00')
            ->where('agreements.0.fundingTimeline.0.milestones.0.events.3.detail', 'Rice seed purchase')
            ->where('agreements.0.fundingTimeline.0.milestones.0.events.3.files.0.downloadUrl', route('investor-evidence.show', [$team, $receipt]))
            ->where('agreements.0.fundingTimeline.1.cycleName', 'Pepper')
            ->has('agreements.0.fundingTimeline.1.milestones', 1)
            ->has('agreements.0.fundingTimeline.1.milestones.0.events', 2)
            ->where('agreements.0.fundingTimeline.1.milestones.0.events.1.label', 'Awaiting investor decision'));

    $this->get(route('investor-evidence.show', [$team, $evidence]))->assertDownload('inspection.pdf');
    $this->get(route('investor-evidence.show', [$team, $receipt]))->assertDownload('seed-receipt.pdf');
    $this->actingAs($otherInvestor)->get($url)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('agreements', 1)
            ->has('agreements.0.fundingTimeline', 1)
            ->has('agreements.0.fundingTimeline.0.milestones', 1)
            ->where('agreements.0.fundingTimeline.0.milestones.0.id', $otherPhase->id)
            ->has('agreements.0.fundingTimeline.0.milestones.0.events', 1));
    $this->get(route('investor-evidence.show', [$team, $evidence]))->assertNotFound();
    $this->get(route('investor-evidence.show', [$team, $receipt]))->assertNotFound();
});

test('the funding timeline leaves older unassigned milestones unassigned and hides rejected evidence', function () {
    $this->withoutVite();
    Storage::fake('local');

    $team = Team::factory()->create();
    $investor = User::factory()->create();
    $team->members()->attach($investor, ['role' => TeamRole::Investor]);
    $farm = Farm::factory()->for($team)->create();
    $agreement = InvestorAgreement::factory()->for($team)->for($farm)->for($investor, 'investor')->create();
    $phase = FundingPhase::factory()->for($team)->for($farm)->for($agreement)->create([
        'investor_visibility_status' => InvestorVisibilityStatus::Rejected, 'externally_released_amount_minor' => 0,
    ]);
    ApprovalRequest::factory()->for($team)->for($agreement)->for($phase, 'subject')->create([
        'request_type' => ApprovalRequestType::FundingRelease, 'status' => ApprovalRequestStatus::Rejected, 'decided_at' => now(),
    ]);
    $evidence = $phase->addMedia(UploadedFile::fake()->create('rejected-inspection.pdf', 4, 'application/pdf'))
        ->toMediaCollection(FundingPhase::EvidenceCollection, 'local');

    $this->actingAs($investor)->get(route('investor-portal.index', $team))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('agreements.0.fundingTimeline', 1)
            ->where('agreements.0.fundingTimeline.0.cycleId', null)
            ->where('agreements.0.fundingTimeline.0.cycleName', null)
            ->where('agreements.0.fundingTimeline.0.milestones.0.events.0.files', [])
            ->where('agreements.0.fundingTimeline.0.milestones.0.events.1.label', 'Rejected'));
    $this->get(route('investor-evidence.show', [$team, $evidence]))->assertNotFound();
});
