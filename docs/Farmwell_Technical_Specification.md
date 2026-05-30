Technical Specification

Farmwell Farm Management System

| **Business / product** | FARMWELL AGTECH SOLUTIONS / farmwell.app                                                                             |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------- |
| **Prepared by**        | Aminu Hussain                                                                                                        |
| **Version**            | 0.1 - Draft for technical validation                                                                                 |
| **Date**               | May 24, 2026                                                                                                         |
| **Purpose**            | Technical handoff document describing the selected stack, packages, architecture, deployment model, and QA approach. |
| **Related BRS**        | Farmwell_Farm_Management_System_BRS.docx                                                                             |

# 1\. Executive Technical Summary

Farmwell should be implemented as a separate Laravel 13/Vue/Inertia SaaS application for farm management and investor transparency. It will use the same approved technology baseline as PowerX, but its domain model, access rules, and integrations are farm-specific.

**Primary technical decision**

Farmwell uses Laravel starter-kit teams as the tenant boundary. Each farm organization belongs to a team, and every farm, production unit, production cycle, diary entry, budget, expense, investor agreement, report, and media attachment must be scoped through that team.

# 2\. Shared Technology Baseline

Both products should be built with the same Laravel/Vue/Inertia architecture so the implementation team can reuse delivery patterns, deployment automation, testing strategy, and operational runbooks while keeping each product as a separate application.

| **Layer**           | **Selected technology**                                                                                     | **Purpose**                                                                                                                    |
| ------------------- | ----------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Backend framework   | Laravel 13.x on PHP 8.3+.                                                                                   | Primary application framework, routing, validation, queues, scheduler, notifications, policies, and Eloquent ORM.              |
| Database            | PostgreSQL.                                                                                                 | Primary relational database for tenant data, reporting queries, JSON metadata, and future geospatial expansion where needed.   |
| Cache and queues    | Database with Laravel queues.                                                                      | Background jobs, email, report generation, media processing, notification retries, and queue visibility.                       |
| Frontend            | Laravel official Vue starter kit with Inertia, Vue 3 Composition API, TypeScript, Tailwind, and shadcn-vue. | Main authenticated app experience with server-side Laravel routes and modern Vue pages.                                        |
| Authentication      | Built-in Laravel auth through Fortify; passkeys enabled with Fortify and laravel/passkeys.                  | Email/password, session auth, passkeys, password reset, email verification, and auth throttling without WorkOS AuthKit.        |
| Tenancy             | Laravel starter-kit teams.                                                                                  | Team/workspace context for organization scoping, member invitations, current-team switching, and tenant-aware policies.        |
| Admin panels        | FilamentPHP 5.x.                                                                                            | Internal admin, operations, dashboards, tables, forms, and back-office workflows.                                              |
| Authorization       | spatie/laravel-permission v7 plus Laravel policies.                                                         | Role and permission management layered with model policies for tenant and record-level access control.                         |
| Media management    | spatie/laravel-medialibrary v11.                                                                                       |
| Reports and exports | spatie/laravel-pdf v2 and maatwebsite/excel 3.1.x.                                                          | PDF statements, certificates, management reports, CSV/XLSX imports and exports.                                                |
| Realtime            | Laravel notifications by default; Laravel Reverb only for true realtime screens.                            | Realtime status updates are optional and should not be added until a workflow clearly benefits from live updates.              |
| Testing and quality | Pest v4, Pest browser tests, Laravel Pint, Larastan/PHPStan, TypeScript checks, and Vite build checks.      | Automated feature, unit, browser, static analysis, formatting, and build validation.                                           |
| Deployment          | Separate Dockerized VPS deployments.                                                                        | Each product has its own repo, domain, database, Redis, queue worker, scheduler, storage path/bucket, backups, and monitoring. |

# 3\. Package Matrix

| **Area**            | **Packages / tools**                                                                                       | **Implementation note**                                                                                                                                   |
| ------------------- | ---------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Laravel core        | laravel/framework:^13.0, laravel/fortify, laravel/passkeys, inertiajs/inertia-laravel                      | Create each app from the official Vue/Inertia starter kit with teams enabled. Use built-in auth/Fortify, not WorkOS AuthKit.                              |
| Admin               | filament/filament:^5.0                                                                                     | Use for internal admin panels, resource management, dashboards, operational tables, and controlled back-office workflows.                                 |
| Access control      | spatie/laravel-permission:^7.0                                                                             | Use roles/permissions for broad capabilities; use Laravel policies for tenant, ownership, and record-level decisions.                                     |
| Media               | spatie/laravel-medialibrary:^11.0                                                                          | Store model-linked media in named collections with private disks and signed/temporary access where needed.                                                               
| PDF and exports     | spatie/laravel-pdf:^2.0, maatwebsite/excel:^3.1                                                            | Use PDF generation for formal statements/certificates/reports; use Excel/CSV for operational exports and imports.  
| Frontend            | @inertiajs/vue3, vue, typescript, tailwindcss, shadcn-vue, lucide-vue-next                                 | Use Vue pages for product UI, shadcn-vue for controls, and lucide icons for actions.                                                                      |
| Testing and quality | pestphp/pest:^4, pestphp/pest-plugin-laravel, pestphp/pest-plugin-browser, laravel/pint, larastan/larastan | Feature/unit/browser tests, code style checks, static analysis, TypeScript checks, and Vite build must pass before release.                               |

# 4\. Shared Architecture Pattern

- Use Laravel routes/controllers/actions as the primary application boundary, returning Inertia Vue pages for the authenticated web app.
- Keep domain logic in service/action classes rather than placing business rules inside controllers, Filament resources, or Vue components.
- Use Filament panels for admin and operations users; use Inertia/Vue for the main product experience seen by customers, students, investors, farmers, and staff.
- Store files through Media Library on private storage by default; expose public files only when the business requirement explicitly allows it.
- Dispatch slow or retryable work to queues: emails, WhatsApp callbacks, media conversions, report generation, certificate/report PDFs, imports, exports, and webhook follow-up.
- Use policies, permissions, and current-team scope on every business model that belongs to a tenant or organization.

# 5\. Deployment and Operations Baseline

| **Component** | **VPS/Docker expectation**                                                    | **Operational requirement**                                                                                     |
| ------------- | ----------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| App runtime   | Nginx plus PHP-FPM container or equivalent VPS service layout.                | Deploy each product independently with its own environment variables and release directory/container image.     |
| Database      | PostgreSQL per application.                                                 
| Workers       | Dedicated queue worker process.                                       | Run under Supervisor/systemd/Docker service with restart policy and failed-job monitoring.                      |
| Scheduler     | Laravel scheduler process or cron invoking schedule:run.                      | Required for reminders, renewals, report jobs, cleanup, and recurring notifications.                            |
| Storage       | S3-compatible object storage or isolated server storage path.                 | Separate buckets/prefixes per product and environment; private default visibility.                              |
| PDF runtime   | spatie/laravel-pdf driver selected during infrastructure setup. 
| Monitoring    | Application logs, uptime checks, queue/failed-job alerts, and error tracking. | Sentry or equivalent is recommended but can be finalized during implementation procurement.                     |

# 6\. Security and Engineering Controls

- Use HTTPS only in production and ensure passkeys are configured against the correct production origin/domain.
- Use Fortify rate limiting and Laravel validation on all auth and sensitive form endpoints.
- Use team-aware authorization checks for every tenant-scoped record and never trust client-supplied team IDs without policy verification.
- Use private media storage for receipts, investor documents, payment proofs, course materials, and certificates unless explicitly public.
- Use activity logs for financial approvals, payment status changes, certificate issuance, report exports, role changes, and destructive actions.
- Use soft deletes for core business records where auditability matters; permanent deletion should be restricted to platform administrators.
- Apply dependency review during project setup because package compatibility can drift after this specification date.

# 7\. Testing and Acceptance Baseline

| **Test type**   | **Required coverage**                                                                            | **Acceptance signal**                                                                                        |
| --------------- | ------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------ |
| Unit tests      | Domain calculations, status transitions, policies, and service/action classes.                   | Pest test suite passes locally and in CI.                                                                    |
| Feature tests   | Authenticated workflows, validation, permissions, media uploads, reports, webhooks, and exports. | Critical happy paths and denial paths are covered.                                                           |
| Browser tests   | Main Inertia/Vue flows that users depend on.                                                     | Pest browser tests cover login, dashboard, core create/edit flows, and public verification where applicable. |
| Static analysis | PHPStan/Larastan, TypeScript, Vite build, and Pint.                                              | CI fails on type, build, format, or analysis errors.                                                         |
| Security tests  | Unauthorized tenant access, private media access, role escalation, webhook signature handling.   | Regression tests prove records cannot leak across teams or roles.                                            |

# 8\. Farmwell Product Architecture

| **Area**                | **Technical design**                                                                                                     | **Key controls**                                                                                             |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------ |
| Tenant workspace        | Each farm organization is represented by a Laravel team. Users may belong to multiple farm organizations if invited.     | All routes, policies, queries, dashboards, and media access must resolve current team before returning data. |
| Main app                | Inertia/Vue mobile-first interface for farm owners, farmers, finance users, and investors.                               | Use responsive layouts for low-bandwidth field usage and clear investor-safe summaries.                      |
| Admin/operations        | Filament panel for platform admin and farm organization admin workflows.                                                 | Restrict tenant support actions and global admin actions with explicit permissions and activity logs.        |
| Domain services         | Use action/service classes for budget variance, approvals, capital recovery, profit distribution, and report generation. | Do not hide business rules inside Vue components or Filament resources.                                      |
| Files/evidence          | Use Media Library collections for farm photos, receipts, transfer proofs, reports, agreements, and diary evidence.       | Default private visibility. Investor access depends on approved visibility flags and policies.               |
| External money tracking | Use external transfer and payout records, not payment processing.                                                        | No wallet, escrow, in-app collection, or disbursement in MVP.                                                |

# 9\. Farmwell Module Map

| **Module**               | **Main responsibilities**                                                                                          | **Primary packages / services**                                        |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------- |
| Organization and users   | Team setup, invitations, farm owner/admin/farmer/investor/finance roles, current-team switching.                   | Laravel starter teams, Fortify, passkeys, Spatie Permission, policies. |
| Farm setup               | Farms, farm types, production units, seasons, production cycles, commodities, locations, photos.                   | PostgreSQL, Eloquent, Media Library, Filament resources.               |
| Farm diary               | Field activity logs, WhatsApp-imported updates, next activity, notes, photos, receipts, status.                    | Inertia/Vue forms, Media Library, queues, Activitylog.                 |
| Budget and expenses      | Budgets, funding phases, expense entries, transfer proofs, reconciliation, variance tracking.                      | Laravel Data DTOs, policies, Activitylog, PDF/Excel exports.           |
| Approvals                | Role/threshold approvals for fund releases, budget overruns, major plan changes, and final distributions.          | Policies, Spatie Permission, notifications, Activitylog.               |
| Investor portal          | Approved expense-level transparency, formal investor statements, evidence feed, progress summaries.                | Inertia/Vue, PDF generation, private media signed access.              |
| Harvest/output and sales | Harvest/output events, commodity sales, buyer records, deductions, revenue, capital recovery.                      | Domain services, PostgreSQL transactions, report exports.              |
| Reports                  | Activity log, budget variance, investor statement, harvest/output, sales, profit distribution, management reports. | spatie/laravel-pdf, maatwebsite/excel, queued jobs.                    |

# 10\. Farmwell Data and Access Model

| **Data group**     | **Core records**                                                                   | **Access rule**                                                                                              |
| ------------------ | ---------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Tenant records     | Team, members, invitations, roles, organization settings.                          | Only team members with the required permission can access or mutate records.                                 |
| Farm operations    | Farm, production unit, production cycle, task, diary entry, input, inventory item. | Farm managers and owners can manage; investors see only approved summaries/evidence for linked agreements.   |
| Financial tracking | Budget, expense, funding phase, external transfer, payout, reconciliation note.    | Finance/admin manage; investors see approved fund usage for their own agreement only.                        |
| Investment         | Investor agreement, capital recovery rule, split formula, distribution record.     | Farm owner/admin and finance manage; investor sees their own agreement and approved calculations.            |
| Evidence           | Photos, receipts, transfer proofs, reports, agreements.                            | Media visibility is collection-specific and policy-checked; private records never use public URLs.           |
| Audit              | Activity log entries, approval history, financial edits, delete attempts.          | Visible to authorized admin/finance roles; not exposed to investors unless explicitly summarized in reports. |

# 11\. Farmwell Integrations

| **Integration** | **MVP approach**                                                                  | **Technical notes**                                                                       |
| --------------- | --------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| WhatsApp        | Start with admin-reviewed manual import or WhatsApp Business API-assisted intake. | Normalize messages/photos into pending diary updates before they become official records. |
| Email           | Account invites, investor reports, reminders, approval notifications.             | Use queued mail and verified sender domain.                                               |
| Object storage  | Store photos, receipts, PDFs, agreements, and reports.                            | Prefer S3-compatible storage with separate bucket/prefix per environment.                 |
| Weather API     | Optional phase 2 field context and reminders.                                     | Use adapter pattern so provider can change later.                                         |
| Maps/GPS        | Optional basic location fields in MVP; full map boundaries later.                 | PostgreSQL is selected to keep future geospatial options open.                            |
| AI summaries    | Optional phase 2/3 investor-friendly summaries from diary entries.                | Must only summarize records visible to the requesting user/team.                          |

# 12\. Farmwell Reporting and Document Generation

- Generate formal investor statements as queued PDF jobs with stored output linked to the investor agreement and reporting period.
- Export operational reports to XLSX/CSV for activity logs, expenses, budget variance, harvest/output, sales, and distributions.
- Keep report data reproducible by storing report parameters, generated-by user, generated-at timestamp, and source record filters.
- Use investor-safe report views that exclude internal notes, unapproved drafts, unrelated investors, sensitive bank details, and unrelated farm records.

# 13\. Farmwell Delivery Phases

| **Phase** | **Technical deliverables**                                                                                                              | **Exit criteria**                                                                                   |
| --------- | --------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| Phase 1   | Laravel starter app, teams, auth/passkeys, roles, farm setup, production units/cycles, diary, media evidence, basic budgets, dashboard. | Farm organization can log real farm operations with photo/receipt evidence and team-scoped access.  |
| Phase 2   | Investor agreements, external transfers, approvals, expense visibility, investor portal, formal reports, PDF/XLSX exports.              | Investor can see approved fund usage and receive a formal statement without seeing private records. |
| Phase 3   | WhatsApp API automation, weather/maps, AI summaries, richer analytics, optional realtime notifications.                                 | Manual admin work is reduced and management reporting becomes more automated.                       |

# 14\. Farmwell Assumptions and Risks

| **Area**            | **Decision / assumption**                                               | **Risk control**                                                                                                 |
| ------------------- | ----------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| Payments            | Farmwell tracks external transfers only.                                | Do not install or expose payment processing until a future regulated payment scope is approved.                  |
| Tenancy             | Starter-kit teams are sufficient for v1 tenancy.                        | Use policies and team scopes consistently; add deeper tenancy package only if team model becomes insufficient.  
| Connectivity        | Mobile web is MVP; offline drafts are phase 2 unless urgently required. | Design forms to save small records reliably and retry media uploads.                                             |
| Investor visibility | Approved expense-level transparency is default.                         | Use separate internal notes and investor-visible fields to avoid accidental disclosure.                          |

# 15\. Technical Reference Anchors

| **Source**                     | **Used for**                                                                   | **URL**                                                             |
| ------------------------------ | ------------------------------------------------------------------------------ | ------------------------------------------------------------------- |
| Laravel starter kits and teams | Vue/Inertia starter kit and team support.                                      | <https://laravel.com/docs/13.x/starter-kits>                        |
| Laravel Fortify and passkeys   | Fortify auth backend, passkey routes, and authentication features.             | <https://laravel.com/docs/13.x/fortify#passkeys>                    |
| FilamentPHP 5.x                | Laravel admin panels, forms, tables, schemas, and dashboards.                  | <https://filamentphp.com/docs/5.x>                                  |
| Spatie Permission v7           | Role and permission package for Laravel.                                       | <https://spatie.be/docs/laravel-permission/v7>                      |
| Spatie Media Library v11       | Eloquent-linked file/media management.                                         | <https://spatie.be/docs/laravel-medialibrary/v11/introduction> 
| Pest v4                        | PHP testing framework with Laravel and browser testing support.                | <https://pestphp.com/docs/pest-v4-is-here-now-with-browser-testing> |
| Laravel Pint                   | Laravel code style fixer.                                                      | <https://laravel.com/docs/13.x/pint>                                |
| Inertia.js                     | Modern monolith bridge between Laravel and Vue.                                | <https://inertiajs.com/docs>                                        |
| shadcn-vue                     | Vue component registry for Tailwind-based UI components.                       | <https://www.shadcn-vue.com/docs/>                                  |
| Spatie Laravel PDF             | PDF generation package with multiple drivers.                                  | <https://spatie.be/docs/laravel-pdf/v2/requirements>                |
                         

# 16\. Sign-Off

| **Role**               | **Name** | **Signature** | **Date** |
| ---------------------- | -------- | ------------- | -------- |
| Product sponsor        |          |               |          |
| Technical lead         |          |               |          |
| Implementation partner |          |               |          |