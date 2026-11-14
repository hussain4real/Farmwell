# Farmwell MVP Implementation Progress

Last updated: 2026-11-13

## Status Legend

- `[x]` Complete
- `[~]` Functionally complete, follow-up identified
- `[ ]` Not started

## Current Assessment

MDSxclusive Farms passed the Chrome photo and returning-session pilot checks on November 13, based on staging records and Suleiman's confirmation relayed by the user. Later that day, the scheduled backup repair and an isolated restore of a fresh R2 archive passed, including Suleiman's account, both diary entries and all three photos. An isolated production deployment and farm-scoped migration rehearsal were subsequently completed; the cutover remains pending approval and the remaining operational checks below. The installed app still fails to launch on Suleiman's Huawei; Chrome is his working pilot access path.

Phase 8 passed private staging acceptance on November 3. Phases 0 through 7 were previously completed against the approved MVP plan. Phase 7 includes queued progress, weekly, risk/decision, and intake-cleanup drafts, with separate review and investor publication controls. Phase 6 achieves its stated goal: harvest/output records, sales, capital-first recovery, investor distributions, partial recovery, loss handling, multiple-sale recovery, and investor acknowledgement visibility are implemented with audit, authorization, browser smoke, static analysis, and 100% coverage gates.

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
| 7. AI Summaries and Assisted Reporting | Add BRS AI capabilities safely after authoritative data exists. | [x] Complete | Accepted on private staging with the live provider, queue recovery, review-gated publication, and investor isolation verified. |
| 8. Reports, Exports, Operations Readiness | Make the system shareable and pilot-ready. | [x] Accepted on staging | Investors downloaded agreement statements and the owner downloaded all five spreadsheets. The staging backup and restore drill passed. |
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

Private staging acceptance, 2026-11-03: the scheduled worker generated all four summary types with OpenAI. A controlled transient failure produced one draft after retry. The investor saw the reviewed update only after publication. A two-investor regression verified agreement isolation on the same team and farm.

Staging application revision `15f6295` served five successful warm portal requests with a median of 1.43 seconds to response headers (range 1.33–2.15 seconds). The cold request took 18.73 seconds. Track cold-start latency and any recurrence of Cloudflare 1101 during operations readiness. [PR #7 and acceptance evidence](https://github.com/hussain4real/Farmwell/pull/7)

- [x] Install `laravel/ai`.
- [x] Publish Laravel AI SDK config and migrations.
- [x] Keep provider/model selection in `config/ai.php` and environment variables.
- [x] Add Farmwell AI agent for investor-safe progress summaries and explicitly reviewed investor publication.
- [x] Add Farmwell AI agent for weekly summaries.
- [x] Add Farmwell AI agent for risk/decision summaries.
- [x] Add Farmwell AI agent for WhatsApp/intake cleanup suggestions using a manually sanitized excerpt.
- [x] Queue all four summary types.
- [x] Store prompts, selected source record IDs, generated output, review status, reviewer, and final published text.
- [x] Require approval and a separate publication action before an investor sees a progress summary.
- [x] Restrict investor progress prompts to investor-safe activities within the agreement scope and recheck source and audience snapshots for publication and display.
- [~] Exclude private notes, unrelated tenants and evidence from selected fields. Intake cleanup sends a user-provided sanitized excerpt; users must remove sensitive data before submission.
- [x] Use Laravel AI SDK fakes/assertions for summary types.
- [x] Maintain 100% coverage without calling live AI providers.

### Phase 8. Reports, Exports, Operations Readiness

- [x] Install Spatie PDF with the DOMPDF renderer.
- [x] Install Laravel Excel for XLSX exports.
- [x] Install Spatie Backup and configure encrypted PostgreSQL and private media archives.
- [x] Add report artifact records with the team, agreement, generator, period, creation time, and saved output.
- [x] Add investor-generated PDF statements for a selected period on an active or completed agreement.
- [x] Add downloadable field diary XLSX snapshots with period, farm, unit, commodity, funding phase, and activity type filters.
- [x] Add budget variance XLSX exports with category totals, expense notes, and linked field activities.
- [x] Add harvest XLSX exports with farm and period filters, quantities sold through period end, and recorded labour costs.
- [x] Add sales XLSX exports with buyer, quantity, price, deductions, payment status, and revenue totals by commodity, unit, and currency.
- [x] Add distribution XLSX exports with recorded capital recovery, profit splits, payout status, and totals by agreement and currency.
- [x] Generate investor statements and all five Excel exports on the database queue, with permission checks, bounded retries, and ready-only downloads.
- [x] Offer an optional reviewed AI progress section on investor PDF statements. Only published, current updates for the agreement and statement period qualify.
- [x] Ensure authorized report/export downloads work.
- [x] Ensure investor reports exclude private data.
- [x] Test the database queue and restore an encrypted backup into an isolated PostgreSQL cluster.
- [x] Activate scheduled staging backups and restore an archive retrieved from the private backup bucket.
- [x] Maintain 100% coverage and static-analysis pass.

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

### Private staging pilot rehearsal, 5 November 2026

**Run `PILOT-20261105-01` reconciled on private staging.** Operator Test recorded the milestone on Staging test farm (farm 1). Aminu Hussain acted for Finance, and Staging Test Investor reviewed it under Staging test agreement (agreement 1). Each used a separate signed-in session. All amounts and evidence were synthetic.

| Step | Verified result |
| --- | --- |
| Mobile field diary | In Chrome Incognito at a 390 × 844 viewport, Operator Test saved diary entry #2 with private JPEG evidence #3. The operator’s direct visits to Finance and team administration both returned 403. |
| Funding and evidence | Finance attached the same image to funding phase #3 and requested ₦120.00. The investor downloaded the approval evidence. Its SHA-256 matched the uploaded file, as did the owner’s download from diary entry #2. |
| Approval and recorded release | On approval request #3, the investor approved ₦90.00. Finance showed ₦90.00 approved and ₦0.00 released before the owner recorded the ₦90.00 simulated external release. Expense #4 recorded ₦30.00 against the phase, budget #1 and diary entry #2. |
| Investor statement | The investor downloaded PDF #8 for 5 November. Opening ₦77.66 + released ₦90.00 − approved spend ₦30.00 = closing ₦137.66. The investor portal also showed ₦137.66. |
| Finance workbook | The owner downloaded budget variance export #9 for the same day. The approved ₦30.00 expense row contained the rehearsal reference and linked activity “#2 Milestone inspection.” Against the ₦1,000.00 full budget, the workbook showed ₦30.00 period spend and ₦970.00 variance. |

**The staging rehearsal passed, and both sponsors approved starting the pilot.** The operator reopened the diary image on the intended phone after desktop Chrome Incognito returned `ERR_BLOCKED_BY_CLIENT`.

| Acceptance item | Result or next action |
| --- | --- |
| Operator on the intended phone | Passed on 5 November 2026. The user confirmed that Chrome on their Redmi phone downloaded `PILOT-20261105-01.jpg` from diary entry #2 and that the opened image displayed “Synthetic field inspection.” |
| Pilot farms | On 5 November 2026 the user confirmed MDSxclusive Farms and Muhammed Sanni’s fish farm, identified in the fish farming investment agreement dated 1 November 2026. |
| Participants and sponsors | The user confirmed all four role assignments for each farm, as listed below. |
| Launch decisions | On 5 November 2026 the user relayed that Suleiman Musa approved starting at MDSxclusive Farms and Muhammed Sanni approved starting at his fish farm. Both want to start as soon as possible. The user reported no conditions. |
| Operational activity | MDSxclusive Farms has a persisted “Land clearing” entry from 11 November and “Pepper nursery watering” from 12 November. These establish recorded pilot use, not the farm's actual operating start date. First recorded activity for the fish-farm pilot has not been verified in this review. |

| Pilot farm | Field operator | Finance lead | Investor representative | Pilot sponsor |
| --- | --- | --- | --- | --- |
| MDSxclusive Farms | Suleiman Musa | Suleiman Musa | Aminu Hussain | Suleiman Musa |
| Muhammed Sanni’s fish farm | Muhammed Sanni | Muhammed Sanni | Aminu Hussain | Muhammed Sanni |

### MDSxclusive Farms Chrome pilot acceptance, 13 November 2026

**Photo and returning-session checks passed.** A read-only staging query on 13 November found diary entry #3, “Land clearing,” and entry #4, “Pepper nursery watering.” Entry #4 was saved on 12 November at 18:00:13 UTC (19:00 Nigeria time) with three JPEG evidence records (#5, #6 and #7). Suleiman's messages and the user's subsequent confirmation provide the physical-phone acceptance evidence below; they are separate from automated tests and database checks.

| Check | Evidence and decision |
| --- | --- |
| Save diary with photos | Passed. Staging contains entry #4 and all three photo records after the upload fix. |
| Retrieve and view photos | Passed by user-reported physical-phone verification. Suleiman confirmed that tapping each photo downloads it; the user then explicitly confirmed that he can open and view the downloaded photos. |
| Return without another SMS | Passed by user-reported Chrome verification. Closing the page and returning without signing out keeps him signed in. This does not establish an elapsed 14-day idle test. |
| Sign in after explicit logout | Expected behavior confirmed: signing out requires a fresh SMS code to sign back in. KudiSMS remains the Nigerian OTP provider. |
| Installed home-screen app | Still unresolved on his Huawei Y7 Prime 2018, Android 8.0: it blinks and closes, including after reinstalling. Chrome works. The cause has not been established; this report does not establish that the phone is rooted or compromised. |

**Release context.** PR #28 and then PR #29 merged into `dev`; the final revision was `45876074be35dd2d6e0596b397578b2996886b40`. Merge-revision [tests](https://github.com/hussain4real/Farmwell/actions/runs/43218849103) and [lint](https://github.com/hussain4real/Farmwell/actions/runs/43218849031) passed. The revision was deployed on 12 November as Cloudflare version `54122988-e99a-4f2e-8323-8762c8824e8a`. These release checks support, but do not replace, the subsequent physical-phone confirmation.

### MDSxclusive Farms production readiness review, 13 November 2026

**Decision: continue the staging pilot; production cutover is not yet ready.** Both sponsors already approved starting their pilots. That approval is recorded above and need not be repeated; it is distinct from approving the production migration and its cutover window. No live cutover was performed. The subsequent isolated rehearsal is recorded below.

| Readiness item | Current evidence and completion criterion |
| --- | --- |
| Repair and verify backups | Completed on 13 November. The [failed scheduled run](https://github.com/hussain4real/Farmwell/actions/runs/43389386993) hit a `libpq-dev` / `postgresql-common` conflict over `/usr/bin/pg_config`. [PR #30](https://github.com/hussain4real/Farmwell/pull/30) removes the unused runner development headers before installing PostgreSQL 18 and tests the same setup in CI. A [fresh backup and restore run](https://github.com/hussain4real/Farmwell/actions/runs/43468528940) passed on merged `main` revision `5019e4d2ff016fa81070abe43929c3e15069611e`; backup health and retention also passed. |
| Restore the real pilot data | Completed on 13 November at 17:02 UTC. The fresh encrypted archive was downloaded from R2 and restored into a disposable PostgreSQL 18 cluster listening only on a private Unix socket. The recovered database dump and all seven private files matched the backup source by SHA-256. PostgreSQL restored the complete dump, including its constraints, and the restored media inventory matched the files. MDSxclusive team #4 retained owner #8 and Suleiman #13 (Admin, verified phone), one farm, diary entries #3/#4 and all three evidence photos #5/#6/#7. No app, queue worker, SMS or email process ran against the restored database; temporary restored data was deleted after verification. This closes the pilot account/record/photo recovery gap left by the 3 November drill. |
| Prepare the production environment and release | The checked Cloudflare account has `farmwell-staging` and its alert worker; both `staging.farmwell.app` and `pilot.farmwell.app` route to the staging app. Neon has a branch named `production`, but its name is not proof of a configured production application. Provision and verify the chosen production hostname, runtime, scoped database role, private media and backup storage, secrets, mail, KudiSMS, queue, scheduler and alerts. Review the release promotion: `dev` is 82 commits ahead of `main` at this review, so deploying the default branch would not reproduce the current pilot build. Require CI on the selected production revision. |
| Rehearse a farm-scoped migration | Inventory MDSxclusive's team, farm, users, verified phone identities, memberships, diary/evidence, related business records and audit links. Preserve identifiers or use an explicit mapping, copy private objects, and handle any encrypted values with the correct application key or a verified re-encryption step. Exclude unrelated tenants and synthetic rehearsal fixtures. Do not transfer queued work, active OTP challenges or browser sessions as farm data. Compare source/destination counts, relationships and file hashes. Preserve Suleiman's account; expect one fresh SMS sign-in on the production hostname. |
| Verify the production workflows | On the prepared destination, prove Suleiman's sign-in and correct farm access, diary save and photo retrieval, session persistence, intended finance/investor permissions, report reconciliation for migrated records, queue processing and alert delivery. Verify unauthorized users cannot retrieve the private evidence. Reuse the synthetic funding rehearsal as prior evidence, but verify the actual migrated configuration and totals separately. |
| Agree cutover and rollback | Obtain approval for the production cutover window, briefly pause staging writes, take the final consistent database/media snapshot, reconcile the destination, and identify the authoritative system before reopening writes. Keep staging and a tested rollback path available; specify how any new production writes would be preserved if rollback is needed. |
| Resolve the phone access exception | Either fix and verify the installed app on Suleiman's phone or explicitly accept Chrome as his supported production access path with clear instructions. His confirmed Chrome checks close the photo/session blockers; they do not close the installed-app issue. |

**Validation of the backup repair.** Merge-revision [tests](https://github.com/hussain4real/Farmwell/actions/runs/43468512529) and [lint](https://github.com/hussain4real/Farmwell/actions/runs/43468512750) passed: 146 application tests, 1,516 assertions and 100% coverage on the backup workflow's `main` branch, plus the PostgreSQL setup and encrypted restore regression. The regression also rejects an incorrect archive password and changed source bytes. These are the backup branch's checks, not a new deployment or acceptance run for the newer application on `dev`. During GitHub's pull-request merge incident, the tested changes were merged through a normal non-forced Git push; GitHub records PR #30 as merged at `5019e4d`.

**Next action:** review the isolated rehearsal below and complete the controlled production workflow and operations checks before requesting cutover approval. Continue preserving MDSxclusive's staging records while Suleiman uses the pilot.

### Isolated production migration rehearsal, 13 November 2026

**Rehearsal passed; staging remains authoritative.** [PR #31](https://github.com/hussain4real/Farmwell/pull/31) combines the current `dev` application with the verified backup repair. Application revision `fd047ec` was deployed only to the new `farmwell-production` Worker as version `144efbae-b5ed-4bc4-84d2-2ba9bdc9611c`, image digest `sha256:469213f8c87b6e622c77cbdda458ea5e300f0f2afa8c33829e9af7aaac981866`. The staging Worker was still last modified on 12 November at 12:36:09 UTC, and both staging and pilot hostnames still route to it.

| Check | Evidence |
| --- | --- |
| Isolated infrastructure | Neon branch `production-rehearsal-20261113` (`br-divine-glitter-bae8evtj`) was created from the verified empty default branch, rather than cloning all staging tenants. Separate R2 buckets `farmwell-production-private` and `farmwell-production-backups` have no public domain enabled. |
| Closed application | `production-preview.farmwell.app` requires a private request credential and permits only GET/HEAD. Public GET and authenticated POST returned 403; protected health and login pages returned 200. Workers.dev and preview URLs are disabled, scheduled triggers are empty, and rehearsal code suppresses queue scheduling and SMS/mail/AI credentials. No OTP, invitation or email was sent. |
| Account and access | Preserved owner #8, Suleiman #13, verified phone identity, password hashes, Admin membership, permission mappings and the accepted phone invitation. The actual Laravel model still resolves Suleiman's phone-invited teams to exactly team #4. The production runtime uses its own application key and scoped database login; it cannot read migrations or update/delete audit events. No selected records needed application-key re-encryption; the migration refuses encrypted identity and AI/report payloads rather than copying them unsafely. |
| Farm data | Reconciled all 95 selected rows field for field: one team/farm, two users, diary entries #3/#4, three media rows, one approved budget and two budget lines, six audit events and the related reference/permission rows. The approved budget totals 53,800,000 NGN minor units (₦538,000.00) in both source and destination. Other teams, pending invitations, sessions, OTP state, passkeys, password resets and queued work were excluded. IDs and sequence advancement were preserved. |
| Photos | Production objects #5/#6/#7 match their staging originals byte for byte, retain the same keys and sizes, and decode as valid images through Laravel's private storage driver. The production storage credential was denied access to the staging bucket. |
| Backup and archive recovery | The new backup credential can read production data/media and write only the dedicated backup bucket. Encrypted archive `Farmwell/2026-11-13-18-59-50.zip` was downloaded from production R2; its database dump and all three photos matched the backup source by SHA-256. It restored unchanged into the separate empty Neon branch `production-archive-recovery-20261113` (`br-tiny-unit-bar2cbk8`). All 95 migrated rows and 46 migration records matched the production rehearsal database, and all three restored photo hashes matched. Restored sessions, jobs, cache and password-reset records were empty. No application or worker was started against the recovery branch. |
| Recovery compatibility limitation | The new Neon schema includes `pg_attver` column options that local stock PostgreSQL 18 rejects. The unmodified archive restored successfully to Neon; it is not yet verified as a portable stock-PostgreSQL recovery. Keep this distinct from the earlier staging archive, which passed its local restore drill. A production recovery runbook must use a compatible Neon target or separately test a supported portable export; do not silently remove schema statements. |
| Validation | Full local quality gate: 326 tests, 3,336 assertions, 100% coverage, static analysis, formatting, lint, types and build passed. Thirteen Worker checks and the disposable PostgreSQL migration regression passed. Revision `fd047ec` passed [application/image CI](https://github.com/hussain4real/Farmwell/actions/runs/43487943397) and [quality CI](https://github.com/hussain4real/Farmwell/actions/runs/43487943456). |

**Recovery custody.** Runtime credentials are in the new Worker's encrypted secret store and the rehearsal worktree's ignored `.env.production`; the separate backup credential and archive password are in its ignored, owner-only `.env.backup`. Transfer these into the designated production operator's password manager before removing that worktree. The archive and isolated Neon recovery branch are retained. No recovery secret is committed or included in this record.

**Before cutover:** review and merge the preparation PR, agree the final production hostname and release revision, configure production-specific KudiSMS/mail and monitoring, verify authorized sign-in and write workflows in a controlled window, and activate production backup scheduling with the tested Neon recovery procedure. Decide whether Chrome is the accepted access path for Suleiman's Huawei. Obtain explicit cutover approval, briefly freeze staging writes, import a fresh consistent copy into a new empty destination and repeat reconciliation; this rehearsal copy will become stale while the pilot continues. The migration command intentionally refuses to overwrite a populated database. Retain staging and the final encrypted snapshot until acceptance, and define how to preserve any new production writes before a rollback. No public cutover or production sign-off is implied by this rehearsal.

### Production readiness after PR #31, 13 November 2026

**Merged and verified; no cutover.** PR #31 merged into `dev` at `505b77b66df8cdc31120676dceccd82090ae6ec8`. Merge-revision [tests](https://github.com/hussain4real/Farmwell/actions/runs/43491122433) and [lint](https://github.com/hussain4real/Farmwell/actions/runs/43491122532) passed. That exact revision was deployed to the isolated production preview as Worker version `9d22ade2-28c0-4903-bf14-c6639a37a288`, image `sha256:d823e475d83176f293aa42ed03f685c450a9b7ff7981ed67e663021704a44eb1`. Public requests and authenticated POST requests returned 403; credentialed health and login GET requests returned 200. The first cold health request took 42.7 seconds and the following warm login request took 1.0 second. This does not establish acceptable cold-start performance for launch.

- **Backup scheduling:** [PR #32](https://github.com/hussain4real/Farmwell/pull/32) merged the production-only workflow onto default branch `main` at `041e8bfd6abfbbd66d09697196129c1d3f7a607c`. Both merge CI checks passed. Its checkout is pinned to verified application revision `505b77b`; it does not promote the older `main` application. The restricted `production-backups` environment uses a read-only production database login, production-media read access and production-backup write access. Daily backup runs at 01:47 UTC; an independent age check runs at 13:47 UTC. [First live run](https://github.com/hussain4real/Farmwell/actions/runs/43492879355) passed backup, archive integrity, health and retention checks and wrote `Farmwell/2026-11-13-19-31-40.zip` (15,519,631 bytes). The unchanged Neon restore of the earlier production archive remains the recovery evidence; the new scheduled archive has not received a separate restore drill.
- **Application writes and reports:** the exact merged container image ran the real diary action against the isolated production database and private bucket, inside a transaction. A synthetic PNG uploaded and read back with the same hash. Suleiman's membership allowed field work and an unrelated identity was denied. The migrated budget reconciled to NGN 538,000 and produced a valid XLSX workbook. The transaction rolled back, the temporary R2 object was deleted, and activity/media/audit counts remained 2/3/6. R2 still contains only the three migrated photos. This was an application-runtime check, not an authenticated browser workflow or a new sign-in.
- **Queue recovery:** a real `queue:work` process in the merged image used a unique synthetic queue on the isolated production database. A first-attempt failure recovered on attempt two; a separate terminal failure was recorded as failed-job ID 1 for monitoring verification. No staging jobs were touched. Cloudflare application queue scheduling remains disabled, and this local container probe does not prove the deployed Cloudflare queue scheduler.
- **Mail and monitoring preparation:** `mail.farmwell.app` is ready according to Cloudflare's DNS checks. A separate production monitor checks the private gateway, service-bound Laravel health, failed-job watermark and R2 backup freshness; regression tests cover sender isolation, disabled delivery, stale archives and recovery. Real email delivery remains disabled pending approval. Production SMS and Laravel business-mail delivery remain unverified.
- **Session behavior:** authenticated sessions use the existing 14-day idle policy; the 120-minute default is for anonymous sessions. No staging session or OTP challenge was copied to production. Production-browser sign-in and persistence still need a controlled live check.

**Still required before launch:** confirm the final hostname and authorization for production trial SMS/email delivery; verify real sign-in, authenticated browser writes and private evidence access; verify the deployed queue path and alert delivery; resolve or accept cold-start latency; accept Chrome as Suleiman's supported access path or verify the installed app. Then obtain explicit cutover approval, freeze staging writes, take a fresh consistent snapshot, import into an empty destination, reconcile again and keep a rollback plan that preserves any subsequent production writes. Suleiman remains on staging throughout preparation.

### Controlled production verification, 14 November 2026

**Controlled checks passed; no cutover.** The user authorized SMS to their saved Nigerian test number and completed the code in regular Chrome. A separate synthetic team and account joined the isolated production preview. Suleiman's pilot stayed on staging version `54122988-e99a-4f2e-8323-8762c8824e8a`; both pilot and staging hostnames still route to `farmwell-staging`.

| Check | Live evidence |
| --- | --- |
| SMS and session | One KudiSMS code reached the user's test number and joined the synthetic farm. Closing and reopening the persistent Chrome profile returned to its dashboard without another SMS. Explicit logout returned to phone sign-in. The secure, HTTP-only authenticated session cookie had the configured approximately 14-day lifetime; this was not an elapsed 14-day idle test. |
| Mobile diary and evidence | At a 390 × 844 viewport, a synthetic diary entry with a PNG saved and downloaded with matching SHA-256. An unrelated account received 403 for migrated MDSxclusive evidence. A temporary authorized membership then downloaded all three original photos; bytes and SHA-256 matched the migration inventory. After removing that membership, the same evidence request again returned 403. This is browser viewport testing, not a new physical-phone acceptance check. |
| Reports and reconciliation | The deployed queue generated the synthetic activity workbook and the migrated farm's budget workbook. The activity workbook contained the test entry; the budget total reconciled to NGN 538,000 budgeted, zero recorded finance expenses and NGN 538,000 remaining. Diary activity costs are separate from finance expense records. |
| Deployed queue recovery | The real Cloudflare scheduled queue container recovered a controlled first-attempt failure on attempt two. A separate terminal test failure produced failed-job ID 2. Report generation and Laravel business mail also ran through that deployed queue; pending jobs were zero after completion. |
| Email and alerts | The designated responder's Gmail inbox received the production delivery test, the queued Laravel email, a controlled application-error alert, a failed-job alert, and uptime failure/recovery messages. The uptime test temporarily changed only the monitor's health target to a nonexistent path; it did not take the application offline. |

**Bug found and fixed.** Switching farms exposed stale Inertia shared team data: the first report link could contain the previously selected farm's slug and return 404. Shared team and permission values now resolve after route membership middleware selects the requested farm. The regression failed before the fix and passed afterward; live first-navigation report downloads then passed. The local full quality gate passed 327 tests, 3,350 assertions and 100% coverage, along with static analysis, formatting, lint, types and build. All 26 Worker, monitor and warm-up tests passed. The patch remains uncommitted and requires review, commit and CI before release; these local/live results are not merge-revision CI.

**Verification closed.** Live verification version `9d3120a6-8ecd-41f6-8ff9-5b29b1210c70` included the team-context fix on top of merged revision `6d5f19f`. The final private read-only preview is version `7c6f650e-447f-4680-89c4-736b025b9a9b`. Verification dates and application queue schedules were removed, temporary production SMS/recipient/mail secrets were deleted, and the temporary mail API token was revoked. The monitor returned to delivery-disabled rehearsal settings and a ten-minute schedule. Public GET and private POST requests return 403; the controlled error route returns 404. The temporary MDSxclusive membership, invitation and test budget artifact were removed; original activity/photo/audit counts remained 2/3/6. Clearly labelled synthetic fixtures remain in their separate rehearsal team and must be excluded from the final migration.

**Still required before cutover:** review and merge the verification/team-context patch and verify its CI; settle the final hostname, durable production credentials and operational settings; accept the remaining cold-start performance and Chrome access exception or resolve them; and obtain explicit cutover approval. Then freeze staging writes, take a fresh consistent snapshot, migrate into an empty destination, reconcile records/photos and preserve a rollback path. The copied account and records were tested with the user's isolated test identity; Suleiman has not been moved or asked to sign into production.

### Approved production cutover preparation, 14 November 2026

The user approved moving Suleiman and MDSxclusive Farms to production and selected `app.farmwell.app`, leaving the apex domain for a future website. PR #35 records the verification fix; its initial head `94bf770` passed application/container and quality CI. Cutover configuration now supports invite-only SMS on the full workspace with email login and normal role authorization, forwards safe requests from the old pilot hostname, blocks replay of old submissions, and freezes the selected farm on staging before its final migration. Regression coverage includes operator finance/administration denial, removed-member SMS denial, external Inertia navigation and public production health checks. The expanded local gate passed 328 tests, 3,390 assertions and 100% coverage; 29 Worker/monitor/warm-up checks passed.

The fresh destination is Neon branch `production-live-20261114` (`br-jolly-hat-bavreso9`), with dedicated `farmwell-production-live-private` media and `farmwell-production-live-backups` buckets. Scoped application and read-only backup logins are separate. Rehearsal data and credentials remain recoverable separately. Staging preflight still found two MDSxclusive activities and three photos, last updated on 12 November; no AI summaries or report artifacts require encryption migration. A pooled schema migration failed with a cached-plan error and was successfully resumed over a direct connection, without modifying staging.

Cutover must retain the source freeze after migration, reconcile all selected rows and object hashes, switch backup configuration to the fresh destination and complete a new backup before opening writes. Before any production writes, rollback can restore staging routing and remove its freeze. After production writes, close destination writes and preserve its database/media snapshot first; do not simply reopen the stale staging copy. Original staging and isolated rehearsal recovery artifacts remain available. This preparation record does not itself assert that traffic has moved; the live outcome must be recorded below.

### Approved production cutover, 14 November 2026

**MDSxclusive Farms is now on production.** After the user approved the move and selected the hostname, staging writes for team 4 were frozen and in-flight requests drained before the final copy. The empty live destination reconciled all 95 selected rows, including the owner and Suleiman's accounts, one team, one farm and two diary activities. All three original photo objects matched the source byte lengths and SHA-256 values. No rehearsal fixtures, sessions, OTP challenges or queued jobs were copied. Scoped database grants and private-media authorization passed; the production media credential cannot access the staging bucket.

The production deployment is version `8ebb09ba-9d2b-47ca-afe3-d462dfe16509` from commit `1e97e92`, whose three CI checks passed. `app.farmwell.app` serves the application, and `pilot.farmwell.app` forwards safe requests there with their path and query intact. Old POST submissions return 409 instead of being replayed. Staging version `94ba0649-27c9-4732-8107-965f1a295f0f` preserves the selected farm's write protection and forwards its reads to production; other staging teams remain available. The apex domain remains reserved for the website. PR #35 remains open against `dev`; this authorized deployment is not a merge.

The fresh staging backup succeeded in run `43583888046`. Production backup credentials and schedules now target the separate live database and buckets. Production run `43584924101` created `Farmwell/2026-11-14-10-23-00.zip`; downloading and decrypting the archive verified all four encrypted entries, the PostgreSQL dump header, and all three photo hashes. This is archive verification, not a second database restore drill. The earlier isolated restore evidence remains recorded above. Preserve the source and rehearsal recovery artifacts; any rollback after production writes must first preserve those new writes.

Live health returned 200 with Laravel's expected body. The root opens phone sign-in; the email alternative also loads. Regular Chrome checks passed at desktop and 390-pixel mobile widths, including the Nigerian numeric field with a ten-digit limit and no horizontal overflow. The production monitor version `ce0beeb0-cfc1-44b6-916b-32bee0d3683b` passed gateway, application, failed-job and backup checks with delivery enabled. The application scheduler is configured to run every minute. KudiSMS and the fourteen-day authenticated idle policy remain in place.

**Participant follow-up:** Suleiman must complete one fresh SMS sign-in at the production hostname and confirm his diary entries and photos there. His successful staging sign-in and physical-phone photo checks do not constitute this new production confirmation. Chrome remains his supported access path; installation on his older handset is not claimed as resolved.

### Phase 8 staging acceptance and recovery

Private staging accepted the Phase 8 reporting workflows on November 3. The scheduled queue generated seven artifacts and their SHA-256 hashes matched the files downloaded through the owner and investor sessions. Only the opted-in statement contained the published AI update. Both PDFs showed the approved spend for the assigned agreement. Direct investor requests for all five owner workbooks returned 403. The unassigned agreement’s statement list and a download URL under that agreement returned 404. [Phase 8 pull request and CI](https://github.com/hussain4real/Farmwell/pull/11)

**Staging recovery evidence, 3 November 2026.** PR #8 merged the backup workflow onto `main` at `839f74b`. Post-merge tests (run `41611716541`) and lint (run `41611716421`) passed. The first live backup (run `41611718354`) wrote `Farmwell/2026-11-03-09-32-17.zip` to the private backup bucket. The downloaded archive restored into an isolated PostgreSQL cluster with 41 migrations, one farm, one agreement and 29 AI summaries, matching staging. Staging had no private media rows. A temporary R2 object matched its original SHA-256 after recovery and was removed from the source bucket. The recovery password is held in macOS Keychain under `Farmwell staging backup archive` for `hussain4real/Farmwell`.

**Backup operations.** The staging workflow runs daily at 01:17 UTC on the default branch. Configure the `staging-backups` GitHub environment with the variables and secrets named in `.github/workflows/staging-backup.yml`. Use a direct Neon endpoint with a read-only login that can dump every Farmwell table and sequence, a read-only media bucket credential, and a separate read/write credential for a dedicated private backup bucket. Store the archive password in an operator password manager for recovery. Grant access to this environment only to trusted deployment branches. Workflow failures require operator attention. An independent 13:17 UTC check flags an empty destination or a latest backup older than two days. Retention keeps every archive for seven days, then one per week for four weeks.

**Restore procedure.** Download the chosen archive into a private workspace and decrypt it with the saved password. Restore `database.dump` to a new empty database using `pg_restore --exit-on-error --single-transaction --no-owner --no-privileges`. Copy the contents of `media/` to a new private bucket with the same object keys. Reapply Farmwell’s scoped database grants and supply the original Laravel application key from the secret store. Keep workers and outbound notifications disabled while checking reports, roles, and protected media in the restored environment. The automated drill in `tests/backup-restore.sh` exercises the restore using synthetic data and local storage.
