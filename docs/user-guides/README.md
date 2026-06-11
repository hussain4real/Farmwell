# Farmwell User Guides

Applies through: Phase 5 - Investor Agreements and Approvals  
Last reviewed: 2026-06-11  
Audience: All Farmwell users, support users, and implementation maintainers  
Related app areas: Dashboard, Farms, Field Diary, Tasks, WhatsApp Intake, Finance, Investors, Approvals, Investor Portal, Team Settings

Farmwell is currently documented through Phase 5: tenant setup, farm operations, diary/tasks/intake, finance tracking, investor agreements, investor approvals, and agreement-scoped investor transparency.

These guides describe current product behavior only. Harvest, sales, capital recovery, AI summaries, formal reports, exports, backups, marketplace workflows, and public investment workflows are not current user workflows in this guide set.

## Guide Map

| User group | Current platform role | Start here |
| --- | --- | --- |
| Farm owner | Owner | [Owner and Admin Guide](owner-admin-guide.md) |
| Farm organization admin | Admin | [Owner and Admin Guide](owner-admin-guide.md) |
| Farm manager / farmer | Member or Admin | [Farm Operations Guide](farm-operations-guide.md) |
| Operations / log keeper | Member or Admin | [Farm Operations Guide](farm-operations-guide.md) |
| Finance / admin user | Owner or Admin | [Finance Guide](finance-guide.md) |
| Investor | Investor | [Investor Guide](investor-guide.md) |
| Support / implementation maintainer | Owner, Admin, or support context | [Permissions and Visibility](permissions-and-visibility.md) |

Small teams may give one person more than one responsibility. Use the permissions guide to decide which app areas they should be able to access.

## Current Feature Map

| Workspace | Current purpose | Main guide |
| --- | --- | --- |
| Dashboard | High-level operational and finance summary | Owner and Admin Guide |
| Farms | Farms, production units, commodities, production cycles, and plan changes | Owner and Admin Guide |
| Field Diary | Activity records, evidence uploads, and activity history | Farm Operations Guide |
| Tasks | Farm task calendar, task status changes, and delay reasons | Farm Operations Guide |
| WhatsApp Intake | Pending update review, conversion, and rejection | Farm Operations Guide |
| Finance | Finance overview, variance, carry-forward, and recent financial activity | Finance Guide |
| Finance Budgets | Budgets and budget lines | Finance Guide |
| Finance Expenses | Expenses, categories, receipts, and investor visibility | Finance Guide |
| Finance Funding Phases | Funding phase tracking and investor approval triggers | Finance Guide |
| Finance External Transfers | Off-platform transfer records, proof, and reconciliation | Finance Guide |
| Investors | Investor agreements, agreement documents, and approval settings | Owner and Admin Guide |
| Approvals | Internal approval queue and decision tracking | Owner and Admin Guide |
| Investor Portal | Investor-only view of assigned approved agreement records | Investor Guide |
| Team Settings | Team membership, invitations, settings, profile, and security | Owner and Admin Guide |

## Key Concepts

- A team is the tenant boundary. Farmwell data should be read and changed only inside the current team.
- A farm groups production units, production cycles, activities, finance records, and investor-linked work.
- A production cycle represents the season or operating cycle being planned and tracked.
- Field diary entries are official activity records. WhatsApp intake entries are pending records until reviewed and converted.
- Evidence files are private. Users should use Farmwell download links rather than sharing storage paths.
- Finance records track budgets, expenses, funding phases, receipts, external transfers, proof, reconciliation, variance, and carry-forward balances.
- Farmwell tracks external money movement only. It does not collect funds, hold balances, provide escrow or wallet services, or disburse funds inside the platform.
- Investor agreements define what an investor may see. Investor visibility is always agreement-scoped and review-gated.
- Investor-safe summaries are for external transparency. Internal notes are for farm operations and should not be copied into investor-facing fields.
- Audit events preserve sensitive changes such as role, settings, finance, agreement, approval, and visibility updates.

## Not Yet Current Workflows

The following are planned in later phases and should not be described to users as available now:

- Harvest and production output records.
- Sales records, buyer tracking, and sales proceeds tracking.
- Capital recovery, profit/loss, and distribution calculations.
- AI-generated weekly, risk, decision, or investor summary drafts.
- Formal PDF reports, Excel exports, backup operations, and investor statements.
- WhatsApp Business API automation.
- Platform administrator, agronomist/advisor, buyer/customer, marketplace, and public investment workflows.

## Maintenance Checklist

Use this checklist after every implementation phase:

- Update `Applies through` and `Last reviewed` on affected guides.
- Add only shipped workflows to role guides.
- Keep future workflows listed only in this README until implemented.
- Cross-check role and visibility claims against current permissions.
- Cross-check workspace names against route-backed app pages.
- Confirm finance language remains external-tracking only.
- Confirm investor language excludes internal notes, unapproved records, unrelated teams, unrelated investors, sensitive bank details, and private records.
- Add Phase 6 content to owner/admin, finance, and investor guides after harvest, sales, capital recovery, and distributions ship.
- Add Phase 7 content after Laravel AI SDK draft/review workflows ship.
- Add Phase 8 content after report, export, and backup workflows ship.
