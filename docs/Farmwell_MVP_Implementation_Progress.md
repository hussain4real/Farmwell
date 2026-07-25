# Farmwell MVP Implementation Progress

Last updated: 2026-06-11

## Status Legend

- `[x]` Complete
- `[~]` Functionally complete, follow-up identified
- `[ ]` Not started

## Current Assessment

Phases 0 through 6 are complete against the approved MVP plan. Phase 6 achieves its stated goal: harvest/output records, sales, capital-first recovery, investor distributions, partial recovery, loss handling, multiple-sale recovery, and investor acknowledgement visibility are implemented with audit, authorization, browser smoke, static analysis, and 100% coverage gates.

The pre-Phase-4 product structure follow-up is complete: Dashboard, Farm Operations, Field Diary, Task Calendar, and WhatsApp Intake now have separate route-backed page boundaries.

## Phase Progress

| Phase | Goal | Status | Notes |
| --- | --- | --- | --- |
| 0. Quality Gate | Make the repo enforce the standard before domain work starts. | [x] Complete | Static analysis, formatting, frontend checks, build, and 100% coverage gates are in place. |
| 1. Tenant, Roles, Settings, Audit | Establish Farmwell's control layer. | [x] Complete | Team-scoped permissions, team settings, audit events, and cross-team denial coverage are implemented. |
| 2. Farm Operating Core | Let a tenant define what is being farmed. | [x] Complete | Farms, units, commodities, production cycles, mixed/intercropped commodities, plan changes, and scoped routes are implemented. |
| 3. Diary, Tasks, Evidence, WhatsApp Intake | Replace WhatsApp-only/spreadsheet-only field operations. | [x] Complete | All functional deliverables and the pre-Phase-4 page-structure follow-up are complete. |
| 4. Budgets, Expenses, Funding, Transfers | Track operating money without wallet/escrow behavior. | [x] Complete | Budgets, funding phases, expenses, receipts, external transfers, proof, reconciliation, variance, carry-forward, and finance pages are implemented. |
| 5. Investor Agreements and Approvals | Add private investor transparency safely. | [x] Complete | Investor role, agreements, approval rules, decisions, approved feeds, comments, and scoped investor portal are implemented. |
| 6. Harvest, Sales, Capital Recovery | Close the farm season financial loop. | [x] Complete | Harvests, sales, capital recovery, investor distribution calculations, acknowledgement approval, and investor portal visibility are implemented. |
| 7. AI Summaries and Assisted Reporting | Add BRS AI capabilities safely after authoritative data exists. | [ ] Not started | Must use Laravel AI SDK and review-gated drafts. |
| 8. Reports, Exports, Operations Readiness | Make the system shareable and pilot-ready. | [ ] Not started | Pending core finance, investor, harvest, and AI data paths. |
| 9. Later Enhancements | Start only after MVP sign-off. | [ ] Not started | Deferred until explicitly selected. |

## Completed Phase Details

### Phase 0. Quality Gate

- [x] Larastan/PHPStan analysis is available through `composer analyse`.
- [x] 100% coverage gate is available through `composer coverage`.
- [x] Aggregate CI gate is available through `composer ci:check`.
- [x] Pint, ESLint, Prettier, TypeScript, Vite build, PHPStan, and Pest coverage are aligned in project scripts.

### Phase 1. Tenant, Roles, Settings, Audit

- [x] Spatie Permission is installed and configured for team-aware permissions.
- [x] Existing team membership and invitation workflows are preserved.
- [x] First-party team settings are implemented.
- [x] Custom `audit_events` records are implemented.
- [x] Team policy/action conventions are established.
- [x] Role, settings, audit, and cross-team denial paths are tested.

### Phase 2. Farm Operating Core

- [x] Tenant farms can be created.
- [x] Production units can be created under farms.
- [x] Commodities and farm types are available.
- [x] Production cycles can be created under farms.
- [x] Mixed/intercropped commodity planning is supported.
- [x] Production plan changes capture reason, impact, old values, and new values.
- [x] Routes and write actions are scoped to the current team.
- [x] Farm operating dashboard data is tested.

### Phase 3. Diary, Tasks, Evidence, WhatsApp Intake

- [x] Spatie Media Library is installed and configured.
- [x] Private evidence disk is configured as `farmwell_private`.
- [x] Activity/diary entries support farm, unit, cycle, commodity, date, type, description, cost, next action, status, and investor-safe summary.
- [x] Activity evidence uploads are private and tenant-authorized for download.
- [x] Task calendar records support due dates, planned dates, reminders, assignment, status, and investor visibility.
- [x] Delayed, blocked, skipped, and cancelled task statuses require a reason.
- [x] WhatsApp intake records can be queued for review.
- [x] Pending WhatsApp intake can be converted into official activity records.
- [x] Pending WhatsApp intake can be rejected with a reason.
- [x] Converted intake copies attached evidence to the official activity.
- [x] Activity, task, intake, conversion, rejection, and evidence authorization flows are audited/tested.
- [x] Mobile/browser smoke path was tested against the Farm Operations page.
- [x] Dashboard, Farm Operations, Field Diary, Task Calendar, and WhatsApp Intake are split into separate route-backed pages.

### Phase 4. Budgets, Expenses, Funding, Transfers

- [x] Finance permissions are added: `finance:view` and `finance:manage`.
- [x] Owner/Admin receive finance permissions by default; Member receives no finance access by default.
- [x] `TeamPermissions` exposes `canViewFinance` and `canManageFinance`.
- [x] Team finance default currency is stored in `team_settings` and defaults to `NGN`.
- [x] New budget, budget line, funding phase, expense, and external transfer records copy the team's configured currency at creation time.
- [x] Money is stored as integer minor units and parsed from decimal form input.
- [x] Expense categories are team-scoped and seeded with the MVP default categories.
- [x] Budgets and budget lines support farm/cycle scoping and category planned amounts.
- [x] Funding phases support requested, approved, externally released, expected, released, and status fields.
- [x] Expenses support category, farm, optional cycle, optional budget/line, optional funding phase, optional activity link, date, status, and receipt uploads.
- [x] Expense receipts are stored on the private `farmwell_private` media disk and downloaded only through finance authorization.
- [x] External transfers support direction, type, status, counterparty, reference, proof, farm/cycle/budget/funding/expense links, and external-only terminology.
- [x] Transfer reconciliations update transfer status and preserve reconciliation amount/status/date.
- [x] Budget variance service reports budgeted, spent, balance, and variance totals and category breakdowns.
- [x] Carry-forward service calculates release less spend across funding phases.
- [x] Route-backed Finance, Budgets, Expenses, Funding Phases, and External Transfers Inertia pages are implemented.
- [x] Dashboard includes a high-level finance summary without becoming the finance workspace.
- [x] Financial settings, budgets, budget lines, funding phases, expenses, receipts, external transfers, transfer proof, and reconciliations are audited.
- [x] Cross-team finance references and private receipt/proof downloads are denied and tested.

### Phase 5. Investor Agreements and Approvals

- [x] Investor role/access behavior is added with portal-only default permissions.
- [x] Navigation is permission-aware: internal users see internal workspaces; investor-only users see the investor portal.
- [x] Investor agreement models, migrations, factories, casts, and relationships are implemented.
- [x] Agreements support team, investor, farm, optional cycle, committed/funded amounts, currency, capital-first recovery, configurable 40/60 default profit split, responsibilities/notes, dates, status, and private documents.
- [x] Approval rules support default threshold configuration and investor-required expense categories.
- [x] Funding phases, expenses, external transfers, and production plan changes can link to investor agreements and visibility status.
- [x] Approval requests are created for funding releases, threshold/category expenses, material budget overruns, and material plan changes.
- [x] Assigned investors can approve, reject, or request clarification; decisions update subject visibility and are audited.
- [x] Internal approval queue and investor management pages are route-backed Inertia pages.
- [x] Investor portal shows only the assigned investor's active/completed agreements and approved scoped records.
- [x] Approved expense-level transparency includes category, amount, status, safe description, and approved receipt evidence.
- [x] Investor comments/questions are supported on approved agreement updates and reviewable records.
- [x] Private agreement documents, receipts, activity evidence, and transfer proof use scoped download authorization.
- [x] Internal notes, private records, unapproved records, inactive agreements, unrelated investors, and cross-team records are denied or excluded.
- [x] Feature and unit tests cover investor invitation/role sync, agreements, document upload, approval settings, approval request creation, decisions, comments, portal rendering, private evidence denial, relationships, casts, enums, and safe feed projection.

### Phase 6. Harvest, Sales, Capital Recovery

- [x] Harvest/output records are implemented with farm, unit, cycle, commodity, investor agreement, date, stage, sequence, quantity, unit, labour cost, status, notes, and investor visibility fields.
- [x] Recurring and staged harvest entries are supported through typed harvest stage values.
- [x] Harvest evidence uploads are stored on the private `farmwell_private` media disk and downloaded only through authorized farm evidence routes.
- [x] Sales records are linked to harvest/output records and inherit farm, cycle, commodity, agreement, team, and currency context.
- [x] Sales support quantity, unit price, gross amount, deductions, net amount, buyer, reference, payment status, notes, and private evidence.
- [x] Recording sales updates harvest status to partially sold or sold based on cumulative sold quantity.
- [x] Capital-first recovery service handles configured funded capital before calculating profit shares.
- [x] Configurable investor/farm profit-share percentages are applied after capital recovery.
- [x] Distribution records are created per sale/agreement with gross/net sale, previous recovery, capital recovered, unrecovered capital, profit, investor share, farm share, status, currency, and investor visibility fields.
- [x] Partial capital recovery, losses before full recovery, multiple sales, and profit splits after recovery are covered.
- [x] Distribution acknowledgement approval requests are created and assigned to investors.
- [x] Investor approval of distribution acknowledgements updates distribution visibility, status, and acknowledgement timestamp.
- [x] Investor portal now includes approved harvest records, visible sale records, distribution records, and capital recovery totals.
- [x] Dashboard and navigation expose the harvest workspace to authorized internal roles.
- [x] Feature and unit tests cover harvest permissions, sale workflows, recovery calculations, distribution acknowledgement, investor portal visibility, private evidence access, model relationships, casts, enums, loss handling, no-agreement sales, and full-sale status transitions.
- [x] Browser smoke verified the Herd Harvests page renders the expected sections with no console errors.

## Planned Phase Details

### Phase 7. AI Summaries and Assisted Reporting

- [ ] Install `laravel/ai`.
- [ ] Publish Laravel AI SDK config and migrations.
- [ ] Keep provider/model selection in `config/ai.php` and environment variables.
- [ ] Use Sub-agents as needed for specialized tasks or data access.
- [ ] Add Farmwell AI agent for investor-safe progress summaries.
- [ ] Add Farmwell AI agent for weekly summaries.
- [ ] Add Farmwell AI agent for risk/decision summaries.
- [ ] Add Farmwell AI agent for WhatsApp/intake cleanup suggestions.
- [ ] Queue long-running AI summary work.
- [ ] Store prompts, selected source record IDs, generated output, review status, reviewer, and final published text.
- [ ] Ensure AI outputs are drafts only and never publish directly to investors.
- [ ] Ensure investor-facing prompts only include policy-approved records.
- [ ] Exclude private notes, unrelated tenant data, unrelated investor data, sensitive bank details, and unapproved evidence from prompts.
- [ ] Use Laravel AI SDK fakes/assertions in tests.
- [ ] Maintain 100% coverage without calling live AI providers.

### Phase 8. Reports, Exports, Operations Readiness

- [ ] Install Spatie PDF.
- [ ] Install Laravel Excel.
- [ ] Install Spatie Backup.
- [ ] Add report artifact records.
- [ ] Add investor statements.
- [ ] Add activity exports.
- [ ] Add budget exports.
- [ ] Add harvest exports.
- [ ] Add sales exports.
- [ ] Add distribution exports.
- [ ] Add queued report generation.
- [ ] Add optional AI-generated summary sections for reviewable reports.
- [ ] Ensure authorized report/export downloads work.
- [ ] Ensure investor reports exclude private data.
- [ ] Test backup and queue paths.
- [ ] Maintain 100% coverage and static-analysis pass.

### Phase 9. Later Enhancements

- [ ] Defer WhatsApp Business API automation until explicitly selected.
- [ ] Defer richer AI chat until explicitly selected.
- [ ] Defer weather integrations until explicitly selected.
- [ ] Defer GPS/geotagging until explicitly selected.
- [ ] Defer offline-first mobile until explicitly selected.
- [ ] Defer marketplace/advisor workflows until explicitly selected.
- [ ] Defer pilot historical import until explicitly selected.
- [ ] Start only after MVP sign-off and a specific next goal is chosen.

## Open Follow-up Before Phase 4

- [x] Split the current combined Dashboard/Farm Operations page into clearer navigation:
  - Dashboard: high-level farm, task, activity, intake, and finance summaries.
  - Farm Operations: farms, units, cycles, commodities, plan changes.
  - Field Diary: activities, evidence, and activity history.
  - Task Calendar: open tasks, delay reasons, completion flow.
  - WhatsApp Intake: pending review, conversion, rejection.
- [x] Keep route names and Wayfinder helpers stable or add redirects if routes move.
- [x] Preserve current test coverage and add Inertia assertions for the new page boundaries.

## Next Recommended Goal

Start Phase 7: AI Summaries and Assisted Reporting.
