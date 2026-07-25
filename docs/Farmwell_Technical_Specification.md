Technical Specification

Farmwell Farm Management System

| **Business / product** | FARMWELL AGTECH SOLUTIONS / farmwell.app |
| ---------------------- | ----------------------------------------- |
| **Prepared by** | Aminu Hussain |
| **Version** | 0.2 - MVP-first technical blueprint |
| **Date** | May 30, 2026 |
| **Purpose** | Technical handoff document translating the Farmwell BRS into an implementation-ready Laravel/Inertia target architecture. |
| **Source of truth** | `docs/Farmwell_Farm_Management_System_BRS.pdf` |
| **Implementation posture** | Target blueprint. The installed starter app foundation is identified separately from planned production packages and infrastructure. |

# 1. Executive Technical Summary

Farmwell is a multi-tenant farm management and investor transparency SaaS platform. The system turns farm plans, WhatsApp-style updates, photos, receipts, activity logs, budgets, external transfer proofs, harvest records, sales records, and investor agreements into one controlled operating record.

The MVP technical design covers two actionable product phases:

- **Phase 1: SaaS operating record** - tenant setup, farm setup, production units, production cycles, diary entries, task calendar, evidence uploads, budget categories, expenses, WhatsApp update intake, and basic dashboards.
- **Phase 2: Investor transparency** - investor accounts, investment agreements, phased funding, approvals, approved fund-usage visibility, budget variance, formal investor reports, capital recovery, and configurable profit-share calculations.

Phase 3 capabilities such as WhatsApp Business API automation, AI summaries, weather reminders, geotagged evidence, offline-first mobile apps, marketplace workflows, and advisory integrations are roadmap items unless explicitly pulled into an implementation milestone.

**Primary technical decision**

Farmwell uses Laravel starter-kit teams as the tenant boundary. Each farm organization maps to a team. Every farm, production unit, production cycle, diary entry, task, budget, funding phase, expense, transfer proof, investor agreement, approval, report, harvest/output record, sale, distribution, risk, issue, and media attachment must be scoped through the current team before data is read or changed.

**MVP business guardrails**

- Farmwell manages private invite-based farm-investor relationships. It must not expose public investment marketplace workflows in MVP.
- Farmwell tracks external bank transfers, manual releases, payout records, receipts, proofs, and reconciliation notes. It must not collect, hold, escrow, wallet, or disburse funds inside the platform in MVP.
- Investors see approved expense-level transparency for their own agreements only. They must not see internal notes, drafts, unrelated farms, unrelated investors, sensitive bank details, or unapproved records.
- WhatsApp support begins as an admin-reviewed manual intake workflow. WhatsApp Business API/webhook automation is a later enhancement.

# 2. BRS Alignment and Scope

The BRS defines Farmwell as the trusted operating record for farm work and farm investment. The technical specification must therefore prioritize structured records, narrative context, approval history, evidence, and investor-safe reporting instead of building only dashboards.

## 2.1 Source Facts Reflected in the Design

| **BRS source fact** | **Technical implication** |
| ------------------- | ------------------------- |
| Farm 1 includes maize intercropped with pepper and eggplant; Farm 2 includes rice planning. | Production cycles must support mixed commodities, intercropping, multiple units, changing production methods, and recurring harvests. |
| The investment model is capital-first, then configurable profit split such as 40/60 or 30/70. | Investor agreements must store recovery rules and split formulas; distribution services must allocate sales proceeds to unrecovered capital before profit. |
| Phase 1 land preparation had budgeted, spent, and balance amounts. | Budgets, phases, expenses, and variance reports must be queryable by farm, cycle, phase, category, and period. |
| WhatsApp updates changed the operating plan from immediate rice to maize first. | Plan changes must capture reason, impact, approver, investor-visible explanation, and linked risk/decision records. |
| Photos evidence clearing, burning, tilling, Farm 2, and nursery work. | Evidence must link to exact activities, expenses, harvest/output records, sales, plans, or reports with visibility controls. |

## 2.2 In Scope for MVP Blueprint

- Multi-tenant SaaS account setup for farm organizations and invited users.
- Farm, production unit, production cycle, commodity, season, and farm-type setup.
- Mobile-first diary/journal with field activity, evidence, costs, labour, inputs, remarks, next activity, status, and investor-safe summary fields.
- Task calendar, reminders, delayed-task handling, and next-action visibility.
- Budget planning, funding phases, expenses, receipts, transfer proofs, reconciliation, carry-forward balances, and budget-versus-actual reporting.
- Investor onboarding, agreements, capital recovery, configurable profit sharing, investor dashboards, investor reports, and approved transparency feed.
- Role- and threshold-based approvals for fund releases, material overruns, major plan changes, investor-approval-required expenses, and final distributions.
- Inventory/input and farm asset tracking sufficient to link purchases and usage to diary activities.
- Risk, issue, and decision logs for operational risks, material changes, and investor notifications.
- Harvest/output, storage/sale status, sales, buyers, deductions, proceeds, capital recovery, and distributions.
- Formal reports and exports for operations, finance, investors, harvest/output, sales, and distributions.
- Optional pilot import/backfill for MDSxclusive Farms historical Excel, Word/PDF, WhatsApp, and photo records.

## 2.3 Out of Scope for MVP Blueprint

- Public investment marketplace, regulated offer listing, escrow, wallet, payment collection, or in-app fund disbursement.
- IoT sensors, drones, satellite imagery, automated disease detection, or forced anti-tamper/geotag checks.
- Government subsidy, insurance, tax, full accounting, or formal compliance integrations beyond exportable reports.
- Guaranteed yield or return predictions. The system may show scenarios, assumptions, actuals, and variance, but not promises.
- Logistics/delivery marketplace flows beyond recording buyers, sales, deductions, proceeds, and evidence.

# 3. Current Foundation vs Target Architecture

The repository currently contains the Laravel/Inertia team-auth foundation. The target blueprint includes planned production packages and infrastructure that must be added deliberately during implementation.

| **Area** | **Current repository foundation** | **Target production blueprint** |
| -------- | --------------------------------- | -------------------------------- |
| Runtime | PHP 8.5, Laravel 13.12.0. | Keep PHP 8.5/Laravel 13 and add production deployment hardening. |
| Web stack | Inertia Laravel 3.1, Vue 3.5, TypeScript, Vite, Tailwind CSS 4.3. | Mobile-first Inertia/Vue product UI with Wayfinder route helpers and typed props. |
| Authentication | Fortify 1.37, passkeys, email/password, verification, password reset, two-factor authentication. | Use Fortify auth with passkeys enabled against the production origin/domain. |
| Tenancy | Teams, team invitations, current-team switching, team roles, team middleware. | Treat teams as tenant boundary for all Farmwell domain data and media access. |
| Database | SQLite local foundation. | PostgreSQL for production tenant data, reporting queries, JSON metadata, and future geospatial options. |
| Queues/cache | Laravel database-backed queue/cache tables exist. | Queue slow/retryable work: mail, notifications, reports, exports, media processing, import jobs, and WhatsApp intake processing. |
| Admin operations | No Filament package installed yet. | Filament is planned for internal/platform admin and controlled back-office workflows. |
| Authorization | Team roles/policies are present; `spatie/laravel-permission` is not installed yet. | Use Spatie Laravel Permission with Laravel policies for the target role/permission model. |
| Media | `spatie/laravel-medialibrary` is not installed yet. | Use Spatie Media Library on private disks for evidence collections, signed access, conversions, and report attachments. |
| Reports/exports | `spatie/laravel-pdf` and `maatwebsite/excel` are not installed yet. | Use Spatie Laravel PDF for formal PDF reports and Laravel Excel for XLSX/CSV exports. |
| Testing and analysis | Pest 4, PHPUnit 12, Laravel test foundation; `larastan/larastan` is not installed yet. | Feature, unit, policy, report, approval, visibility, and browser tests must cover critical workflows; Larastan/PHPStan must run in CI. |
| Deployment | Local starter app; Herd/dev tooling available. | Separate production app deployment with HTTPS, worker, scheduler, private storage, backups, logs, uptime checks, and error tracking. |

## 3.1 Package Policy

Planned packages must not be treated as installed until composer/npm changes are made. Add dependencies only when the implementation phase needs them and after checking current compatibility.

| **Capability** | **Preferred target** | **Install timing** |
| -------------- | -------------------- | ------------------ |
| Internal admin panel | Filament | Add before building platform/farm-organization admin resources. |
| Role/permission matrix | `spatie/laravel-permission` plus Laravel policies | Add before implementing the Farmwell role/capability matrix beyond the starter team foundation. |
| Evidence/media collections | `spatie/laravel-medialibrary` | Add before production evidence uploads need collections, conversions, responsive images, or signed media access. |
| PDF reports | `spatie/laravel-pdf` | Add before formal investor/farm management PDF statements are implemented. |
| Excel/CSV exports | `maatwebsite/excel` | Add when activity, budget, investor, harvest/output, sales, and distribution exports are implemented. |
| Audit log | Custom `audit_events` tables | Build before financial edits, approvals, agreement changes, report exports, role changes, and delete attempts go live. |
| Backups | `spatie/laravel-backup` | Add before investor/financial data goes live; configure database and private media backup destinations, monitoring, and cleanup. |
| Tenant settings | First-party organization settings tables | Prefer explicit `team_settings` or domain-specific settings tables over a settings package so tenant scoping, policies, validation, and audit history stay clear. |
| Data/DTO layer | Plain Form Requests, API/Inertia resources, and action inputs | Do not add a DTO package by default; introduce typed value objects only where calculations need stronger invariants. |
| Static analysis | `larastan/larastan` | Add before domain implementation work and require it in CI for PHP type/static checks. |
| Coverage driver | PCOV PHP extension on Laravel Herd PHP 8.5 | Use for local coverage runs and enforce 100% test coverage for app code. |

# 4. Architecture Pattern

## 4.1 Application Boundaries

- Use Laravel routes, controllers, form requests, policies, actions, and services as the server-side application boundary.
- Return Inertia Vue pages for authenticated product workflows.
- Use single-purpose action classes where possible for business workflows such as creating cycles, recording diary entries, requesting funding, approving expenses, reconciling transfers, generating reports, and calculating distributions.
- Keep business rules in action/service classes rather than controllers, Vue components, or Filament resources.
- Use Form Requests for validation/authorization, API or Inertia resources for response shaping where useful, and plain arrays/value objects for action inputs unless a calculation requires a dedicated value object.
- Use Wayfinder-generated route/action helpers for frontend navigation and mutations.
- Use queued jobs for report generation, exports, email, media processing, import/backfill, and future WhatsApp API/webhook work.

## 4.2 Tenant Scoping

- Every tenant-owned table must include `team_id` directly or derive team ownership through a required parent relation.
- Route model binding for tenant-owned records must be constrained by current team.
- Policies must check current-team membership, role/capability, record ownership, and investor-agreement linkage.
- Queries for dashboards, reports, feeds, exports, and background jobs must explicitly scope to the team and authorized agreement.
- Client-supplied team IDs are never trusted without server-side current-team and policy verification.

## 4.3 Domain Services

Implement high-risk business rules as isolated services with focused tests:

- Budget variance calculation.
- Funding phase balance and carry-forward calculation.
- Approval routing by role, threshold, category, phase, farm type, and investment agreement.
- Capital-first recovery calculation.
- Configurable profit-share calculation.
- Investor-visible feed projection.
- Formal report data snapshot generation.
- Plan-change impact tracking.
- Audit event recording.

# 5. User Roles and Access Model

| **Role / user group** | **Primary capabilities** | **Access boundaries** |
| --------------------- | ------------------------ | --------------------- |
| Farm owner | Own farms, approve plans, invite users/investors, review profit/loss, approve routine spend. | Team-scoped full access except platform-only controls. |
| Farm organization admin | Manage tenant settings, users, farms, roles, subscriptions, and organization-wide reports. | Team-scoped admin access; no cross-tenant access. |
| Farm manager / farmer | Record activities, upload evidence, request funds, manage tasks, report risks, log harvests. | Can manage assigned farms/units/cycles; cannot expose private records to investors directly. |
| Operations/log keeper | Enter historical records, clean diary data, organize photos, reconcile source documents. | Team-scoped operational access; approval-sensitive changes require authorization. |
| Finance/admin | Approve/reconcile expenses, transfer proofs, sales proceeds, payout records, and distributions. | Team-scoped financial access with audit requirements. |
| Investor | View approved fund usage, evidence, progress, capital recovery, expected profit share, and reports. | Only records linked to their own investment agreements and approved for investor visibility. |
| Agronomist/advisor | Review plans, risks, pest/disease/animal-health notes, recommended actions, and activity history. | Team-scoped advisory access; financial visibility only if explicitly granted. |
| Platform administrator | Manage tenants, subscriptions, system configuration, support workflows, and system-level audit. | Cross-tenant access must be explicit, permission-gated, and audited. |

Small family farms may assign multiple roles to one person, but authorization must still evaluate the action being performed rather than assuming one role can do everything.

# 6. Functional Module Blueprint

## 6.1 Organizations, Teams, and Users

**BRS coverage:** BO-07, FR-INV-01, NFR-03, NFR-09.

- Reuse Laravel teams for tenant account setup, invitations, current-team switching, roles, and membership.
- Store organization settings for default currency, approval thresholds, report branding, investor visibility defaults, and farm-type templates.
- Support users belonging to multiple farm organizations through team membership.
- Enforce all Farmwell domain records through current-team-aware middleware, route model binding, policies, and query scopes.

## 6.2 Farm, Production Unit, and Production Cycle Management

**BRS coverage:** BO-01, FR-FRM-01 through FR-FRM-07.

- Farms capture business name, owner/contact details, registration details, farm type, location, notes, status, and photos.
- Production units model plots, fields, pens, ponds, houses, or other units with type, size/capacity, suitability, status, history, and optional GPS/map references.
- Production cycles belong to a farm and one or more production units, with season, farm type, commodity/commodities, production method, planned dates, actual dates, expected output, status, and plan version.
- Farm type templates must support crops, livestock, poultry, aquaculture, mixed farms, and plantations without hard-coding only crop terms.
- Mixed cultivation and intercropping are first-class requirements; a cycle can contain multiple commodities and commodity-specific expected outputs.
- Plan changes capture reason, date, requester, approver, budget impact, expected-output impact, timeline impact, risk link, and investor-visible explanation.

## 6.3 Diary, Journal, Evidence, and WhatsApp Intake

**BRS coverage:** BO-02, BO-03, FR-DIA-01 through FR-DIA-07, acceptance criteria for mobile field updates and WhatsApp conversion.

- Diary entries support date, farm, production unit, production cycle, commodity, activity type, description, inputs used, labour used, cost, remarks, next activity, status, internal notes, and investor-safe summary.
- Activity statuses include planned, in progress, completed, delayed, blocked, skipped, and changed.
- Evidence attachments support photos, videos, receipts, invoices, documents, captions, uploader, captured/recorded date, linked record type, and visibility.
- Evidence must link to exact activities, expenses, harvest/output events, sales, plan changes, or reports.
- MVP WhatsApp intake is manual/admin-reviewed: a user forwards or uploads WhatsApp text/photos into pending intake records; an authorized admin/log keeper normalizes them into official diary entries and evidence.
- Pending WhatsApp intake records must preserve original text, source date if known, sender/importer, attachments, review status, reviewer, converted record links, and rejection/clarification reason.
- Voice-to-text and quick mobile entry are later enhancements unless implementation capacity allows them without delaying Phase 1.

## 6.4 Budgets, Funding Phases, Expenses, and External Transfers

**BRS coverage:** BO-04, FR-FIN-01 through FR-FIN-08.

- Budgets attach to farm seasons or production cycles and contain categories such as tools, seeds/stock, land preparation, herbicides, planting, labour, feeding, health care, fertilizers, pest control, harvest/output handling, transport, and contingency.
- Funding phases store phase name, planned budget, milestone definition, requested amount, approved amount, externally released amount, spent amount, balance, carry-forward balance, status, approval state, and supporting evidence.
- Expenses store date, activity link, phase, category, vendor, payment method, amount, receipt/evidence, approver, investor visibility, reconciliation state, and audit metadata.
- External transfer records store payer/payee, bank reference, amount, date, transfer proof, status, reconciliation note, related agreement/phase, and payout direction.
- Farmwell must never create a product surface that suggests it processes or holds money in MVP. UI labels should use terms like "external transfer", "recorded release", "proof", "payout record", and "reconciliation" rather than wallet or escrow language.
- Budget-versus-actual views must work at farm, production cycle, production unit, phase, category, and period levels.

## 6.5 Investor Portal and Investment Agreements

**BRS coverage:** BO-02, BO-05, FR-INV-01 through FR-INV-09.

- Investment agreements capture investor, farm/season/cycle scope, amount committed, amount funded, capital recovery rule, profit split, funding model, role responsibilities, notes, start/end date, status, and attached agreement document.
- Investors can view only farms, seasons, agreements, updates, financial records, evidence, and reports permitted by their agreement and approved visibility rules.
- Capital-first recovery is the default agreement model: sales proceeds recover investor capital before profit sharing is calculated.
- Profit splits are configurable per agreement and must support examples such as 40/60 and 30/70 after capital recovery.
- Investor dashboards show committed capital, released/externally transferred capital, approved spend, remaining balance, phase completion, evidence feed, production status, risks, sales, capital recovery, and expected profit share.
- Investor comments/questions can be added after core read-only transparency is stable; they must be scoped to an agreement/update and visible to authorized farm users.

## 6.6 Approval and Governance Workflow

**BRS coverage:** FR-APP-01 through FR-APP-06.

- Approval thresholds are configurable by amount, category, phase, farm type, investment agreement, and request type.
- Investor approval is required for funding releases, material budget overruns, major plan changes, final profit distribution, and expense categories marked investor-approval-required.
- Farm owner/admin or finance/admin can approve routine day-to-day expenses that are within approved budget and below configured threshold.
- Finance/admin users can reconcile external transfer proofs, receipts, sales proceeds, and payout records.
- Approval records must store request type, requester, approver, threshold applied, requested amount, approved amount, status, comment, timestamp, and linked subject record.
- Delegation, escalation, and clarification requests are should-level enhancements; the data model should not prevent them.

## 6.7 Inputs, Inventory, Labour, and Assets

**BRS coverage:** FR-INVTRY-01 through FR-INVTRY-05 and labour/contractor scope.

- Track inputs such as seeds, herbicides, pre-emergence chemicals, fertilizers, insecticides, fungicides, foliar spray, nets, sacks, fuel, feed, veterinary inputs, and aquaculture consumables.
- Track tools/equipment such as hoes, cutlasses, knapsack sprayers, pumps, hoses, nets, machinery hire, and other farm assets.
- Purchase records store quantity, unit cost, total cost, supplier, purchase date, storage location, linked receipt, budget category, and expense link.
- Activity entries can consume inventory quantities and show remaining balances after usage.
- Labour and contractor records capture self labour, hired labour, feeding, transport, service costs, contractor/vendor details, and activity links.
- Low-stock alerts and reorder suggestions are optional later enhancements.

## 6.8 Task Calendar, Reminders, Risks, Issues, and Decision Logs

**BRS coverage:** FR-TASK-01 through FR-TASK-04, FR-RISK-01 through FR-RISK-04.

- Generate planned tasks from production plans and allow authorized users to add manual tasks.
- Reminders cover planting windows, transplanting, fertilizer application, spraying intervals, feeding schedules, vaccination, pond checks, weeding, harvest readiness, missing receipts, funding approvals, and delayed activities.
- Delayed, moved, cancelled, skipped, or replaced tasks require a reason when they affect plan, budget, investor visibility, or expected output.
- Risks capture severity, probability, owner, mitigation, due date, status, budget impact, timeline impact, and investor-notification status.
- Plan changes can link to risks, including the BRS example where rice-first changed to maize-first because neighboring farms created bird/rodent pressure.
- Material risk or decision updates must be convertible into investor-visible explanations after approval.

## 6.9 Harvest, Output, Sales, and Distribution

**BRS coverage:** BO-05, FR-HAR-01 through FR-HAR-05.

- Harvest/output records store farm type, commodity, production unit, cycle, date, quantity, unit, quality notes, labour cost, storage/sale status, and evidence.
- Recurring harvests must support crops such as pepper and eggplant with repeated weekly entries.
- Multi-stage harvests must support rice-style stages such as cutting, gathering, threshing, bagging, storage, and sale.
- Sales records store buyer, commodity/output, quantity, unit price, gross amount, deductions, net amount, payment status, receipt/payment evidence, and linked harvest/output records.
- Distribution calculations store agreement, capital recovered, unrecovered capital, gross profit, net profit, split formula, investor share, farmer/farm share, payout status, and supporting approval.
- Capital recovery and profit sharing must be recalculated through a tested service whenever sale/distribution inputs change.

## 6.10 Reports, Dashboards, and Exports

**BRS coverage:** BO-06, FR-RPT-01 through FR-RPT-07, reporting requirements, acceptance criteria.

- Farm owner dashboard: farm status, cycles, current phase, activities, delayed tasks, budget, actual spend, balance, harvest/output status, sales, risks, and expected returns.
- Investor dashboard: committed capital, externally released capital, approved spend, balance, evidence feed, phase completion, production status, capital recovery, and expected profit share.
- Required reports: farm activity log, funding phase report, budget variance report, investor fund usage report, production progress report, risk/issue report, harvest and sales report, profit distribution report, and formal investor statement.
- Reports must be reproducible by storing parameters, generated-by user, generated-at timestamp, source record filters, team, agreement scope, and output artifact.
- Investor report views must exclude internal notes, unapproved drafts, unrelated investors, sensitive bank/personal details, unrelated farms, and unapproved evidence.
- `spatie/laravel-pdf` should be added when formal PDF report implementation starts; `maatwebsite/excel` should be added for XLSX/CSV report exports.

## 6.11 Optional Pilot Import and Backfill

**BRS coverage:** pilot context, source references, open question for data import.

- Pilot import is optional and separate from core MVP product behavior.
- Import tooling may support Activity Log.xlsx, MDSxclusive Farms 2026 Plan.doc, Farm Investment Proposal PDF, WhatsApp messages, and supplied photos.
- Imported records should land in reviewable staging tables or pending records before they become official activities, budgets, agreements, or evidence.
- Imports must preserve source filename, row/page/message reference when available, imported-by user, imported-at timestamp, normalized target record, and confidence/review status.
- Do not block Phase 1 farm operations on historical import unless the pilot launch explicitly depends on it.

# 7. Data Model Blueprint

This section defines target domain entities and relationships. It is not a migration specification; implementation should still design migrations in small, tested increments.

| **Entity** | **Minimum fields / relationships** | **Visibility and audit rules** |
| ---------- | ---------------------------------- | ------------------------------ |
| Team / organization | Business name, registration details, owner, users, roles, settings, default currency, farms. | Tenant root; soft-delete and settings changes should be audited. |
| Team setting | Team, setting key/group, typed value, validation context, changed by, changed reason. | First-party tenant settings must be policy-checked and audited when they affect approvals, reporting, visibility, or integrations. |
| Farm | Team, name, owner/contact, farm type, location, contacts, description, photos, status. | Team members by role; investor only through approved agreement summaries. |
| Production unit | Team, farm, type, name, size/capacity, notes, production history, status, optional GPS/map reference. | Team-scoped; investor visibility only through approved cycle/update/report. |
| Production cycle | Team, farm, production unit(s), season, commodity/commodities, planned dates, actual dates, budget, status, expected output. | Material changes require approval/audit and investor-safe explanation when relevant. |
| Activity diary | Team, date, activity type, production unit, cycle, description, inputs, labour, cost, remarks, next activity, evidence, status. | Internal notes separated from investor-safe summary; status edits audited. |
| Task | Team, farm/unit/cycle, title, activity type, planned date, due date, status, reminder settings, delay/cancel reason. | Investor-visible only when approved for progress reporting. |
| Risk / issue / decision | Team, farm/unit/cycle, severity, probability, owner, mitigation, due date, status, impact, investor notification. | Material decisions audited; investor text must be approved. |
| Budget | Team, farm/cycle/season, category, phase, planned amount, assumptions, approval status. | Finance/admin manage; investor sees approved aggregate/phase detail. |
| Funding phase | Team, agreement/cycle, phase name, budget, requested, approved, externally released, spent, balance, milestone, evidence, status. | Investor can see own approved phases and evidence. |
| Expense | Team, date, category, activity, phase, vendor, method, amount, receipt, approver, investor-visible flag, reconciliation state. | Financial edits and deletes audited; investor sees only approved visible expenses. |
| External transfer / payout record | Team, agreement/phase/distribution, payer, payee, reference, amount, date, proof, direction, status, reconciliation note. | Sensitive bank details hidden from investors unless explicitly approved. |
| Investor agreement | Team, investor, farm/season/cycle, amount committed, amount funded, recovery rule, split formula, role responsibilities, notes, documents, status. | Investor sees own agreement; internal notes hidden. |
| Approval | Team, request type, requester, approver, threshold, status, comment, approved amount, timestamp, subject record. | Immutable history; corrections are new events. |
| Inventory/input/asset | Team, item type, name, quantity, unit, unit cost, supplier, purchase date, storage location, receipt, usage records. | Team-scoped; financial details follow expense visibility. |
| Harvest/output | Team, commodity, production unit, cycle, date, quantity, unit, quality, labour cost, storage/sale status, evidence. | Investor-visible when linked to their agreement and approved. |
| Sale | Team, buyer, commodity/output, quantity, price, gross, deductions, net, payment status, proof, linked harvest/output. | Buyer/payment details visible only to authorized roles unless summarized. |
| Distribution | Team, agreement, capital recovered, net profit, split formula, investor share, farm share, payout status, approval. | Final distribution requires investor review/acknowledgement before completion. |
| Evidence/media | Team, file type, file, caption, date, uploader, linked subject, visibility, approval status. | Private by default; no public URLs for sensitive evidence. |
| Report artifact | Team, report type, parameters, generated by, generated at, source filters, file, audience, status. | Investor artifacts must be generated from investor-authorized data only. |
| Audit event | Team, actor, action, subject, old values, new values, reason, timestamp, request metadata. | Required for financial, approval, agreement, status, report, role, and deletion events. |

## 7.1 Status Sets

- Production cycle: draft, planned, active, delayed, changed, completed, closed, cancelled.
- Diary/activity: planned, in progress, completed, delayed, blocked, skipped, changed.
- Funding phase: draft, requested, approved, released externally, partially spent, complete, reconciled, closed.
- Expense: draft, submitted, approved, rejected, reconciled, disputed, voided.
- Approval: pending, approved, rejected, clarification requested, delegated, expired, cancelled.
- Evidence: pending review, approved internal, approved investor-visible, rejected, archived.
- Transfer/payout: recorded, proof attached, pending reconciliation, reconciled, disputed, voided.
- Report: queued, generating, ready, failed, archived.

# 8. Security, Privacy, and Audit Controls

- Enforce HTTPS in production and configure Fortify passkeys with the correct relying party ID and allowed origins.
- Require email verification and strong password/passkey flows for sensitive users.
- Apply rate limiting to authentication, invitation, passkey, approval, and report-generation endpoints.
- Use Laravel policies on every tenant-owned model and Spatie Laravel Permission for platform-level, finance-sensitive, and tenant role/capability checks.
- Store receipts, transfer proofs, agreement documents, reports, and private farm evidence on private disks/object storage.
- Serve investor-visible files through signed/temporary URLs after policy checks.
- Never expose sensitive bank/personal details to investors unless a specific approved report requires it.
- Financial records, approvals, agreement changes, activity-status changes, report exports, role changes, and delete attempts must write audit events with actor, timestamp, old value, new value, and reason where applicable.
- Use soft deletes for core business records where auditability matters; permanent deletion is platform-admin-only and audited.
- Background jobs must re-check authorization scope where they generate investor-facing reports or media links.

# 9. Integration Blueprint

| **Integration** | **MVP approach** | **Technical notes** |
| --------------- | ---------------- | ------------------- |
| Email | Account setup, invitations, password reset, investor reports, reminders, weekly summaries. | Use queued mail and verified sender domain. |
| WhatsApp | Manual/admin-reviewed intake from forwarded messages/photos. | Store pending intake records; future Business API webhooks should normalize into the same staging workflow. |
| Cloud storage/media | Required for photos, videos, receipts, agreements, reports, and evidence. | Use private visibility by default with team/agreement policy checks. |
| External bank transfer tracking | Record transfers, releases, payouts, proofs, references, and reconciliation. | Tracking only; no payment processing, wallet, escrow, or disbursement in MVP. |
| Accounting/export | CSV/XLSX/PDF exports for operations and finance. | Start with exportable reports; no formal accounting integration in MVP. |
| Weather API | Phase 2 or later unless simple field context is easy to add. | Use provider adapter so weather source can change. |
| Maps/GPS | Optional basic coordinates in MVP; boundaries/geotagging later. | PostgreSQL target keeps future geospatial options open. |
| AI summaries | Phase 2/3 optional. | Summaries may only use records visible to the requesting user/team/agreement. |

# 10. Deployment and Operations Baseline

| **Component** | **Target expectation** | **Operational requirement** |
| ------------- | ---------------------- | --------------------------- |
| App runtime | Laravel app behind HTTPS with PHP-FPM/Nginx or equivalent managed runtime. | Per-environment `.env`, release rollback, config/cache optimization, secure app key, and session settings. |
| Database | PostgreSQL in production; SQLite can remain local/test where appropriate. | Backups, migrations, least-privilege credentials, and restore testing. |
| Queue worker | Dedicated worker process. | Failed-job monitoring, retry policy, and alerts for report/import/media failures. |
| Scheduler | Laravel scheduler. | Required for reminders, report jobs, cleanup, failed-intake reminders, and recurring notifications. |
| Storage | S3-compatible object storage or isolated private server storage. | Separate buckets/prefixes per environment; private default visibility. |
| Logs/errors | Laravel logs plus uptime/error tracking. | Capture production exceptions, failed jobs, report failures, and integration errors. |
| Backups | Database and private media backups via `spatie/laravel-backup`. | Configure backup destinations, health monitoring, cleanup policy, and restore testing before investor/financial data goes live. |

# 11. Testing and Acceptance Strategy

Every implementation change must be programmatically tested. Use the smallest useful test slice for the changed behavior.

| **Test type** | **Required coverage** | **Acceptance signal** |
| ------------- | --------------------- | --------------------- |
| Unit tests | Capital recovery, profit-share calculation, variance calculation, status transitions, approval routing. | Pure domain services pass edge cases and examples. |
| Feature tests | Team scoping, farm/cycle creation, diary entry, evidence upload, budget/expense, funding phase, approvals, reports. | Happy paths and denial paths pass. |
| Policy/security tests | Cross-team denial, investor-only visibility, private media access, role escalation, financial edit audit. | Investors and unrelated tenants cannot access private records. |
| Browser tests | Login, team switch, dashboard, mobile diary entry, investor portal, approval flow, report download where practical. | Critical user journeys work through Inertia UI. |
| Static analysis | Larastan/PHPStan. | CI fails on static-analysis regressions before release. |
| Coverage gate | Pest/Laravel coverage using PCOV on Laravel Herd PHP 8.5. | Maintain 100% application test coverage; use `php artisan test --coverage --min=100` or the equivalent CI command. |
| Static/build checks | Pint, PHP tests, TypeScript checks, Vite build, ESLint/Prettier. | CI fails on format, type, build, coverage, or test regressions. |

## 11.1 BRS Acceptance Criteria Mapping

| **BRS acceptance criterion** | **Technical verification** |
| --------------------------- | -------------------------- |
| Farm organization creates tenants, farms, production units, cycles, budgets, and roles. | Feature tests for team-scoped setup and role-gated creation. |
| Farmer records activity with cost, remark, next activity, and evidence from mobile. | Feature/browser test for diary entry and private evidence upload. |
| System reproduces 2026 wet-season-style activity log with phase totals and balance. | Report/service test for activity log, funding phase totals, spent amount, and balance. |
| Farm manager requests phase funding with milestone evidence. | Feature test for funding phase request, evidence, approval state, and audit event. |
| Investor views approved released funds, approved spend, balance, evidence, progress, and next activity. | Investor policy/feed tests proving approved own-agreement visibility only. |
| WhatsApp update can be reviewed and converted into an official activity. | Feature test for pending intake review, conversion, source preservation, and rejection path. |
| Plan change logs reason, impact, and investor-visible explanation. | Feature/unit tests for plan-change service, risk link, approval, and visibility. |
| Harvest/output and sales are recorded with commodity, quantity, buyer, amount, and evidence. | Feature tests for harvest, sale, evidence, and report inclusion. |
| System calculates capital recovery and profit sharing from configured agreement. | Unit tests for capital-first distribution and configurable split formulas. |
| Management exports activity, budget, investor, harvest/output, sales, and distribution reports. | Feature tests for report authorization, parameters, queued generation/export, and stored artifact metadata. |
| Formal investor report is generated for selected period or phase. | Feature test for investor-safe report snapshot excluding private records. |
| Financial edits, approvals, and delete attempts are audited. | Audit tests for old/new values, actor, reason, and immutable history. |

# 12. Delivery Phases

| **Phase** | **Technical deliverables** | **Exit criteria** |
| --------- | -------------------------- | ----------------- |
| Phase 1: SaaS operating record | Team-scoped domain models, roles, farm setup, production units, cycles, diary, evidence, task calendar, budgets, expenses, manual WhatsApp intake, basic farm dashboard, audit foundation. | A farm organization can run field operations, log activities with evidence, track budget/spend/balance, and review a searchable activity record. |
| Phase 2: Investor transparency | Investor agreements, external transfers, phased funding, approvals, investor-safe feeds, investor dashboard, budget variance, formal reports, capital recovery, profit-share calculation, exports. | An investor can see approved fund usage and formal statements without seeing private or unrelated records. |
| Phase 3: Growth and intelligence | WhatsApp API automation, AI summaries, weather reminders, richer GPS/geotag evidence, offline/mobile app, marketplace/buyer enhancements, advisor workflows. | Manual admin work is reduced and Farmwell scales toward broader agribusiness workflows after legal/product validation. |

# 13. Open Implementation Decisions

These items remain business decisions, not technical blockers for the MVP blueprint:

- Confirm final brand/legal details: official company name, CAC details, logo, address, and report branding.
- Confirm whether MDSxclusive Farms becomes the first tenant record and which historical records should be imported.
- Confirm default NGN approval thresholds and categories requiring investor approval versus farm owner/admin approval.
- Confirm whether offline drafts are required in MVP or can remain resilient-retry/mobile-web-first.
- Confirm required formal investor report layout, sign-off fields, and export formats.
- Confirm when to install Filament, `spatie/laravel-medialibrary`, `spatie/laravel-permission`, `spatie/laravel-pdf`, `maatwebsite/excel`, `spatie/laravel-backup`, and `larastan/larastan` based on implementation milestone.
- Confirm the first-party settings table shape before settings implementation: one generic `team_settings` table, domain-specific settings tables, or a hybrid.

# 14. Technical Reference Anchors

| **Reference** | **Used for** | **URL** |
| ------------- | ------------ | ------- |
| Farmwell BRS PDF | Product requirements, MVP scope, business rules, acceptance criteria. | `docs/Farmwell_Farm_Management_System_BRS.pdf` |
| Laravel starter kits and teams | Installed app foundation and team-based tenant boundary. | <https://laravel.com/docs/13.x/starter-kits> |
| Laravel Fortify passkeys | Authentication and passkey configuration. | <https://laravel.com/docs/13.x/fortify#passkeys> |
| Inertia v3 | Laravel/Vue page rendering and SPA architecture. | <https://inertiajs.com/docs/v3> |
| Laravel Wayfinder | Typed controller/route helpers for frontend calls. | <https://github.com/laravel/wayfinder> |
| Spatie Media Library | Selected package for evidence, receipt, agreement, report, and media collections. | <https://spatie.be/docs/laravel-medialibrary> |
| Spatie Laravel Permission | Selected package for roles and permissions layered with Laravel policies. | <https://spatie.be/docs/laravel-permission> |
| Spatie Laravel PDF | Selected package for formal investor and farm management PDF reports. | <https://spatie.be/docs/laravel-pdf> |
| Laravel Excel | Selected package for XLSX/CSV imports and exports. | <https://laravel-excel.com/> |
| Spatie Laravel Backup | Selected package for database and private media backup jobs, monitoring, and cleanup. | <https://spatie.be/docs/laravel-backup> |
| Larastan | Selected static analysis package for Laravel/PHPStan checks. | <https://github.com/larastan/larastan> |
| Pest | PHP test framework for feature/unit coverage. | <https://pestphp.com/docs> |
| Laravel Pint | PHP formatting. | <https://laravel.com/docs/13.x/pint> |

# 15. Sign-Off

| **Role** | **Name** | **Signature** | **Date** |
| -------- | -------- | ------------- | -------- |
| Product sponsor | | | |
| Farm operator | | | |
| Investor representative | | | |
| Finance/admin lead | | | |
| Technical lead | | | |
| Implementation partner | | | |
