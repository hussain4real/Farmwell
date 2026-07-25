# Finance Guide

Applies through: Phase 6 - Harvest, Sales, Capital Recovery
Last reviewed: 2026-06-11  
Audience: Finance/admin users and farm organization admins  
Related app areas: Finance, Budgets, Expenses, Funding Phases, External Transfers, Harvests, Investors, Approvals

Use this guide when you plan budgets, record expenses, attach receipts, track external transfers, reconcile proof, review variance, record harvest sales, review capital recovery, and manage investor visibility for finance records.

## Responsibilities

- Keep the team default currency accurate before creating money records.
- Create budgets and budget lines before recording planned-versus-actual variance.
- Record expenses from authoritative receipts or approved spend records.
- Attach receipt evidence to the correct expense.
- Record external transfers with proof and reconciliation status.
- Record sales only against the harvest/output record they belong to.
- Review capital recovery and distribution results after investor-linked sales.
- Link finance records to investor agreements only when the record belongs to that agreement.
- Review investor visibility before exposing finance records externally.

## External-Tracking Rule

Farmwell tracks off-platform money movement. It does not collect funds, hold balances, provide escrow or wallet services, process payment collection, or perform in-platform disbursement.

Use labels such as external transfer, recorded release, proof, receipt, and reconciliation when describing finance workflows.

## Currency

The team finance default currency is stored in team settings and defaults to NGN.

- Budget, funding phase, expense, and transfer records copy the team currency at creation.
- Phases 4 through 6 do not perform currency conversion.
- Check the team currency before creating money records for a new organization.

## Expense Categories

Default expense categories include tools, seeds/stock, land preparation, herbicides, planting, labour, feeding, health care, fertilizers, pest control, harvest/output handling, transport, and contingency.

Categories can be marked as investor-approval-required. No category requires investor approval by default.

## Budgets and Budget Lines

Use Budgets to define planned spend.

- Create a budget for the relevant farm and production cycle when a cycle exists.
- Use farm-level budgeting only for pre-cycle or farm-level spending.
- Add budget lines by category and planned amount.
- Use budget notes and assumptions to preserve planning context.

Budget variance compares planned budget lines against recorded expenses.

## Funding Phases

Use Funding Phases to track staged funding.

- Record requested, approved, expected, and externally released amounts.
- Link a funding phase to a farm and production cycle when available.
- Link it to an investor agreement when the funding phase belongs to that agreement.
- Funding releases linked to agreements can create approval requests.

Carry-forward balances compare released funds against spending across funding phases.

## Expenses and Receipts

Use Expenses for finance totals.

- Choose the correct farm, optional cycle, category, and optional budget line.
- Link to a funding phase when the expense belongs to a funded phase.
- Link to a field activity only when the expense supports that activity.
- Link to an investor agreement only when the expense belongs to that agreement.
- Attach receipts through the private receipt workflow.

Expense receipts are private and should be downloaded only through authorized Farmwell links.

Investor visibility can be private, pending approval, approved, rejected, or clarification requested. Only approved agreement-scoped records should appear in the investor portal.

## External Transfers and Reconciliation

Use External Transfers to track money movement that happened outside Farmwell.

- Record the transfer direction, type, status, counterparty, reference, amount, and date.
- Link the transfer to farm, cycle, budget, funding phase, expense, or investor agreement where appropriate.
- Attach proof through the private transfer proof workflow.
- Reconcile the transfer when the proof and amount are confirmed.

Transfer reconciliation updates the transfer status and preserves reconciliation amount, status, date, and notes.

## Variance and Carry-Forward

Use Finance for summary review.

- Budgeted amount shows planned spend.
- Spent amount comes from expenses.
- Balance shows remaining budget or available phase balance.
- Variance highlights the difference between plan and actual spend.
- Carry-forward shows externally released funds less recorded spend across phases.

Investigate large variance before approving new releases or exposing finance summaries to investors.

## Harvest Sales

Use Harvests to record sales after harvest/output has been logged.

Each sale should include:

- The source harvest record.
- Investor agreement only when the sale belongs to that agreement.
- Sale date.
- Buyer name.
- Quantity and quantity unit.
- Unit price.
- Gross amount.
- Deduction amount when transport, market, handling, or other sale deductions apply.
- Payment status.
- Reference, notes, and private evidence when available.

The sale inherits farm, production cycle, commodity, team, and currency context from the harvest. This keeps sale proceeds tied to the output that generated them.

Sale evidence should support the specific sale. Good evidence includes buyer receipts, market slips, settlement documents, weighing records, or payment proof. Evidence is private and should be downloaded only through authorized Farmwell links.

## Capital Recovery and Distributions

Investor-linked sales trigger capital-first recovery calculations.

Farmwell calculates:

- Sale gross amount.
- Sale net amount after deductions.
- Previous capital already recovered for the agreement.
- Capital recovered from the current sale.
- Unrecovered capital remaining.
- Profit after capital recovery.
- Investor share using the agreement's investor profit-share percentage.
- Farm share using the agreement's farm profit-share percentage.

Capital is recovered before profit is split. If a sale does not fully recover funded capital, the distribution can show unrecovered capital and no profit share. Multiple sales for the same agreement build on previous recovery. Once capital is recovered, later net profit is split using the agreement terms.

Distribution records are created for sales linked to investor agreements. They are not payment instructions, wallet balances, escrow balances, or in-platform disbursements. They are operating records that show how sale proceeds relate to capital recovery and profit-sharing terms.

## Distribution Acknowledgement

Distribution records require investor acknowledgement before they become visible as approved investor-facing distribution records.

- The system creates a distribution acknowledgement approval request for investor-linked sales.
- The assigned investor can approve, reject, or request clarification.
- Approval marks the distribution as acknowledged and approved for investor visibility.
- Rejection or clarification means the distribution should not be treated as investor-approved.

Review distribution records before relying on them for external reporting. Check the harvest, sale, deductions, agreement, funded amount, and profit-share terms.

## Investor Visibility

Investor-linked finance records should be checked before approval.

- Confirm the investor agreement is correct.
- Confirm the category, amount, receipt, and safe summary are appropriate for investor review.
- Confirm harvest, sale, recovery, and distribution records are tied to the correct agreement before making them visible.
- Do not expose sensitive bank details, internal notes, unrelated transfers, or unrelated expenses.
- Use approval requests for funding releases, threshold-triggered expenses, investor-required categories, material budget overruns, and distribution acknowledgements.

## Current Limits

This guide does not cover formal statements, exports, AI summaries, or backup operations because those workflows are not current through Phase 6.
