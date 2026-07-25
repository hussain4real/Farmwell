# Owner and Admin Guide

Applies through: Phase 6 - Harvest, Sales, Capital Recovery
Last reviewed: 2026-06-11  
Audience: Farm owners and farm organization admins  
Related app areas: Dashboard, Farms, Finance, Harvests, Investors, Approvals, Team Settings

Use this guide when you are responsible for setting up the farm organization, inviting users, managing operating records, reviewing finance and harvest activity, and controlling investor access.

## Responsibilities

- Keep team membership and roles current.
- Maintain accurate farm, production unit, commodity, and production cycle records.
- Review material plan changes before they are exposed externally.
- Oversee budgets, expenses, funding phases, external transfer records, and reconciliation status.
- Oversee harvest records, sales, capital recovery, and investor distribution acknowledgement.
- Create and maintain investor agreements.
- Configure approval settings and review approval requests.
- Protect internal notes, unapproved records, and unrelated investor data from investor-facing views.

## Team Setup

Team settings and membership are managed from Team Settings.

- Use invitations to add admins, members, and investors.
- Use Admin for users who manage farm operations, finance, investor agreements, and approval requests.
- Use Member for users who only need farm operations visibility.
- Use Investor only for users who should access the investor portal.
- Review audit events when sensitive settings, roles, finance records, agreements, approvals, or visibility values change.

The Owner role has full team permissions. Admin users have broad team management, farm operations, finance, investor agreement, and approval permissions. Members and Investors have narrower access.

## Farm Operations Setup

Use Farms to define the operating structure before recording diary and finance activity.

- Create farms with the correct type, location, contact, and status details.
- Create production units such as fields, plots, pens, ponds, houses, or other units.
- Create commodities for crops, livestock, aquaculture, mixed farms, or other supported farm types.
- Create production cycles for seasons or operating periods.
- Link mixed or intercropped commodities to the production cycle when relevant.
- Record production plan changes with reason, impact, old values, new values, and investor-safe explanation when the change may affect transparency.

Plan changes can be linked to investor agreements and visibility status. Material plan changes can trigger approval requests.

## Dashboard Use

Use Dashboard for summary checks, not detailed data entry.

- Review current farm, unit, cycle, activity, task, intake, and finance counts.
- Review harvest, sales, capital recovered, unrecovered capital, investor share, and farm share summaries.
- Use the dedicated workspaces for detailed updates.
- Treat dashboard numbers as prompts to inspect the underlying records before making decisions.

## Finance Oversight

Finance workspaces are available to Owner and Admin users.

- Use Finance for the summary, variance, carry-forward, and recent activity view.
- Use Budgets to define planned amounts by category and cycle/farm.
- Use Funding Phases to track requested, approved, expected, and externally released amounts.
- Use Expenses to record actual spend, categories, receipts, and investor visibility.
- Use External Transfers to track off-platform releases, receipts, proof, and reconciliation.

Farmwell does not process payments. It records external transfers and supporting proof only.

## Harvest and Sales Oversight

Use Harvests to review the season output loop.

- Confirm harvest records are tied to the correct farm, unit, cycle, commodity, and investor agreement when applicable.
- Confirm recurring or staged harvest entries use sequence numbers consistently.
- Check harvest evidence before relying on quantity or quality claims.
- Confirm sales are recorded against the correct harvest record.
- Review buyer, quantity, unit price, gross amount, deductions, net amount, payment status, and sale evidence.
- Watch for harvest records that remain unsold, partially sold, or marked as loss.

Farm operations and finance users can both view Harvests when their permissions allow it. Recording harvests is an operations or finance management task. Recording sales and reviewing recovery/distribution totals is a finance management task.

## Investor Agreements

Use Investors to create and maintain private investor relationships.

- Create an agreement for the correct team, investor, farm, and optional production cycle.
- Record committed and funded amounts in the agreement currency.
- Keep the capital-first recovery rule and profit split terms accurate.
- Store role responsibilities, public notes, and internal notes in the correct fields.
- Upload agreement documents through the private document workflow.
- Use active agreements for current investor access.

Investors see only records tied to their own active or completed agreements and approved for investor visibility. Internal notes remain internal.

## Capital Recovery and Distributions

Capital recovery uses the investor agreement terms.

- Funded capital is recovered before profit is split.
- Partial recovery leaves unrecovered capital and no profit share until capital is recovered.
- Multiple sales build on previous capital recovered for the same agreement.
- Profit after capital recovery is split using the agreement investor/farm profit-share percentages.
- Loss or below-capital sales should be reviewed before any investor-facing explanation is approved.

Distribution records summarize the calculation for investor-linked sales. They are operating records, not in-platform payouts or disbursements.

## Approval Settings and Requests

Use Investors for approval settings and Approvals for the approval queue.

- The default approval threshold is configured in team settings and defaults to NGN 500,000.00.
- Expense categories are not investor-required by default; enable investor approval only for categories that require it.
- Funding releases, threshold-triggered expenses, category-required expenses, material budget overruns, material plan changes, and distribution acknowledgements can create approval requests.
- Assigned investors can approve, reject, or request clarification.
- Internal users can review the approval queue and audit decisions.

Approval decisions update investor visibility on the linked subject. Do not manually expose a record to investors unless the agreement, approval state, and record content are appropriate.

Distribution acknowledgement approval marks the distribution acknowledged and approved for investor visibility when the assigned investor approves it.

## Visibility Rules

- Use investor-safe summaries for information investors should see.
- Keep internal notes for private operational context.
- Do not upload sensitive bank details as investor-facing evidence.
- Do not link unrelated farm, cycle, harvest, sale, expense, activity, transfer, or distribution records to an investor agreement.
- Do not expose draft, private, rejected, or clarification-requested records to investors.
- Review agreement scope before sharing receipts, photos, transfer proof, activity evidence, harvest evidence, sale evidence, or distribution records.

## End-of-Phase Maintenance

At the end of each implementation phase, update this guide only for workflows that are actually shipped. Phase 7 should add AI draft, review, and investor-safe summary guidance after those features are implemented.
