# Farmwell MVP Implementation Progress

Last updated: 2026-06-07

## Status Legend

- `[x]` Complete
- `[~]` Functionally complete, follow-up identified
- `[ ]` Not started

## Current Assessment

Phases 0 through 3 are complete against the approved MVP plan. Phase 3 achieves its stated goal: diary/activity entries, task calendar records, delayed-task reasons, private evidence uploads, and WhatsApp intake review/convert/reject flows are implemented and covered by tests.

The pre-Phase-4 product structure follow-up is complete: Dashboard, Farm Operations, Field Diary, Task Calendar, and WhatsApp Intake now have separate route-backed page boundaries.

## Phase Progress

| Phase | Goal | Status | Notes |
| --- | --- | --- | --- |
| 0. Quality Gate | Make the repo enforce the standard before domain work starts. | [x] Complete | Static analysis, formatting, frontend checks, build, and 100% coverage gates are in place. |
| 1. Tenant, Roles, Settings, Audit | Establish Farmwell's control layer. | [x] Complete | Team-scoped permissions, team settings, audit events, and cross-team denial coverage are implemented. |
| 2. Farm Operating Core | Let a tenant define what is being farmed. | [x] Complete | Farms, units, commodities, production cycles, mixed/intercropped commodities, plan changes, and scoped routes are implemented. |
| 3. Diary, Tasks, Evidence, WhatsApp Intake | Replace WhatsApp-only/spreadsheet-only field operations. | [x] Complete | All functional deliverables and the pre-Phase-4 page-structure follow-up are complete. |
| 4. Budgets, Expenses, Funding, Transfers | Track operating money without wallet/escrow behavior. | [ ] Not started | Next domain phase after resolving the page-structure follow-up. |
| 5. Investor Agreements and Approvals | Add private investor transparency safely. | [ ] Not started | Pending Phase 4. |
| 6. Harvest, Sales, Capital Recovery | Close the farm season financial loop. | [ ] Not started | Pending Phase 5. |
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

## Planned Phase Details

### Phase 4. Budgets, Expenses, Funding, Transfers

- [ ] Add budget models and migrations scoped to the current team.
- [ ] Add funding phase models and workflows.
- [ ] Add expense records with category, amount, date, farm/cycle links, and audit trail.
- [ ] Add receipt/evidence uploads using private media storage.
- [ ] Add carry-forward balance calculations.
- [ ] Add variance service for budgeted, spent, balance, and variance values.
- [ ] Add external transfer records without wallet, escrow, payment collection, or in-platform disbursement behavior.
- [ ] Add reconciliation records and transfer proof uploads.
- [ ] Add tenant-scoped budget/expense/funding UI.
- [ ] Add feature/unit/policy tests for totals, balances, variance, transfer proof, reconciliation, auditing, and cross-team denials.

### Phase 5. Investor Agreements and Approvals

- [ ] Add investor role/access behavior.
- [ ] Add investor agreement models and migrations.
- [ ] Add configurable capital-first agreement terms.
- [ ] Add approval request workflow for investor-required expense categories.
- [ ] Add investor-required category configuration.
- [ ] Add approved expense-level investor feed.
- [ ] Add private investor dashboard.
- [ ] Ensure investors can only see their own approved agreement data.
- [ ] Deny private, unapproved, unrelated, and cross-team records.
- [ ] Add feature/unit/policy tests for investor visibility, approval boundaries, and denials.

### Phase 6. Harvest, Sales, Capital Recovery

- [ ] Add harvest/output records.
- [ ] Support recurring harvest entries.
- [ ] Support staged harvest entries.
- [ ] Add sales records linked to harvest/output records.
- [ ] Add capital recovery service.
- [ ] Add configurable profit-share terms.
- [ ] Add distribution records.
- [ ] Handle partial capital recovery.
- [ ] Handle losses.
- [ ] Handle multiple sales.
- [ ] Handle profit splits after capital-first recovery.
- [ ] Add focused unit tests for capital-first recovery calculations.
- [ ] Add feature tests for harvest, sales, recovery, and distribution workflows.

### Phase 7. AI Summaries and Assisted Reporting

- [ ] Install `laravel/ai`.
- [ ] Publish Laravel AI SDK config and migrations.
- [ ] Keep provider/model selection in `config/ai.php` and environment variables.
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

Start Phase 4: Budgets, Expenses, Funding, and Transfers.
