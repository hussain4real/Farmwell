# Business Requirements Specification

## Farmwell Farm Management System

##### Business / product FARMWELL AGTECH SOLUTIONS / farmwell.app

```
Purpose Draft BRS for a multi-tenant farm management and investor transparency SaaS web
application.
```
```
Prepared by Aminu Hussain
```
```
Version 0.1 - Draft for validation
```
```
Date May 23, 2026
```
```
Pilot context MDSxclusive Farms and family-backed farm operations are used as discovery inputs, but
Farmwell is intended for the broader farm/investor market.
```
```
Source inputs Activity Log.xlsx, MDSxclusive Farms 2026 Plan.doc, Farm Investment Proposal PDF,
WhatsApp updates, and supplied farm/nursery photos.
```
##### Document note

##### This BRS converts a real farm investment and operations workflow into software requirements. It is not legal, tax,

##### or investment advice. Farmwell is currently scoped to track off-platform funding and payouts, not collect, hold, or

##### disburse money inside the platform.

## 1. Executive Summary

#### Farmwell is intended to be a multi-tenant farm management and investor transparency SaaS

#### platform for the broader agricultural market, not only one family farm. The immediate inspiration is

#### a family-backed farm where updates currently move through WhatsApp messages, photos, PDF

#### proposals, Word plans, and Excel activity logs. The system should turn those fragmented updates

#### into one controlled record of what is planned, what was funded, what was done, what was spent,

#### what was harvested, what was sold, and what investors are owed.

#### The platform should help farm owners and managers run all farm types day-to-day while giving

#### investors a clear view of how their funds are being used. A farmer should be able to keep a diary or

#### journal with photos, activities, costs, labour, inputs, plots or production units, production cycles, risks,

#### harvests or production output, and sales. An investor should be able to view fund releases, budget

#### versus actual spend, approved evidence of completed work, expected returns, capital recovery, and

#### configurable profit-sharing calculations.

##### Core business idea

##### Farmwell should become the trusted operating record for farm work and farm investment: a shared system where

##### farm activity is logged at source and investor confidence is built through timely, evidence-backed transparency.

## 2. Background and Business Context

#### The source farm plan covers a 2026 wet-season operation with Farm 1 planned as 1 acre of maize

#### intercropped with pepper and eggplant, and Farm 2 planned as half an acre of rice. The accompanying


#### proposal uses a capital-first investment model: investor funds are recovered first from sales, then profit

#### is shared according to an agreed formula. The WhatsApp exchange also shows real operational

#### change: because neighbouring farms planned maize before rice, the farmer proposed planting maize

#### first to reduce bird and rodent pressure, then raising rice nursery later for transplanting around the first

#### week of August.

#### These inputs show the exact software problem: farm work changes quickly, decisions are

#### context-sensitive, and investors need enough visibility to understand why money is being spent or why

#### a cultivation plan changed. The system must preserve both structured records and narrative context.

### 2.1 Source Facts Captured

##### Source Key information reflected in requirements

```
Farm plan Farm 1: maize, pepper, eggplant on 1 acre. Farm 2: half-acre rice plan. Planned input cost:
NGN 606,500. Expected combined profit range: about NGN 801,000 to NGN 1,681,000 based
on plan assumptions.
```
```
Investment proposal Capital-first model, staged funding, investor recovery before profit split, initial 40/60 proposal,
and requested adjustment to 30/70 from chat context. Product direction is capital-first with
configurable split per agreement.
```
```
Activity log 2026 wet-season activity log with Phase 1 land preparation. Budgeted NGN 150,000, spent
NGN 144,000, balance NGN 6,000. Entries include herbicide, clearing/spraying, tilling Farm 1
and Farm 2, knapsack sprayer, tools, transport and feeding.
```
```
WhatsApp updates Clearing completed, burn-off completed, harrowing/tilling in progress, planting readiness,
decision change from immediate rice to maize first then rice after maize harvest.
```
```
Photos Evidence of cleared land, burning of clearings, tilled plots, Farm 2, and pepper/eggplant
nursery under netting.
```
### 2.2 Confirmed Product Decisions

##### Decision area Confirmed direction

```
Market scope Farmwell is a SaaS product for the broader market, supporting many farms, farm
organizations, managers, and investors.
```
```
Money movement Farmwell will track external bank transfers/manual payments and payout records. It will not
collect, hold, or disburse funds inside the platform for MVP.
```
```
Investor visibility Default to approved expense-level transparency: investors see approved categories, amounts,
receipts/photos, activity summaries, material plan changes, harvest/sales, capital recovery,
and profit-share calculations for their own investments only.
```
```
Private records Investors should not see internal private notes, draft or unapproved expenses, other investors'
records, sensitive bank/personal details, or messy operational conversations.
```
```
Profit model Use a capital-first model by default, then apply a configurable profit split per investment
agreement.
```
```
WhatsApp updates Farmers should be able to submit or forward updates through WhatsApp, in addition to mobile
web entry.
```
```
Evidence Normal photo uploads are sufficient for MVP. Geotagging, anti-tamper checks, and forced
timestamp verification can come later.
```
```
Farm types The product should support all farm types over time: crop, livestock, poultry, aquaculture,
mixed farms, and plantations.
```

##### Decision area Confirmed direction

```
Approvals Use role- and threshold-based approvals. Farm owner/admin approves routine in-budget
spend; investor approval is required for fund releases, major expenses, material overruns,
major plan changes, and final profit distribution.
```
```
Reports Formal investor and farm management reports are required, not only dashboard views.
```
```
Product positioning Position Farmwell as farm management and investor transparency software for many farms,
under FARMWELL AGTECH SOLUTIONS.
```
## 3. Business Objectives

##### ID Priority Business requirement

```
BO-01 Must Provide a single digital record for farms, plots/production units, crop or farm production cycles,
activities, expenses, evidence, funding, harvest/output, sales, and investor returns.
```
```
BO-02 Must Improve investor trust by showing how released funds are used, supported by receipts, photos,
notes, activity logs, and budget variance tracking.
```
```
BO-03 Must Help farmers manage daily work through a mobile-friendly farm diary, task calendar, activity
journal, input tracking, and next-action reminders.
```
```
BO-04 Must Support phased funding so investors can release capital only after prior milestones or field
activities are confirmed.
```
```
BO-05 Must Calculate capital recovery, profit, loss, and profit sharing according to configurable investment
agreements.
```
```
BO-06 Should Provide farm owners with dashboards for operational performance, production progress,
spending, output forecast, sales, and cash position.
```
```
BO-07 Must Operate as a multi-tenant SaaS product that can serve many farms, farm managers, investors,
cooperatives, and agribusiness partners.
```
```
BO-08 Could Use AI-supported summaries to convert field notes, WhatsApp-like updates, and activity logs
into investor-friendly progress reports.
```
## 4. Scope

### 4.1 In Scope

- Multi-tenant SaaS account setup for multiple farm businesses and investor groups.
- Farm, plot, field, production-unit, and production-cycle setup.
- Support for crop farms, livestock, poultry, aquaculture, mixed farms, and plantations through

#### configurable farm types and production cycles.

- Farm diary/journal with activities, photos, videos, notes, location, weather notes, and next activity.
- Budget planning, phased funding, expenses, receipts, and budget-versus-actual tracking.
- Investor onboarding, investment agreements, fund release requests, capital recovery, and

#### profit-share tracking.

- Input inventory including seeds, fertilizer, herbicide, insecticide, tools, equipment, fuel, and

#### consumables.


- Labour and contractor activity recording, including self labour, hired labour, feeding, transport, and

#### service costs.

- Task calendar for clearing, burning, plowing/tilling/harrowing, planting, transplanting, spraying,

#### weeding, fertilizing, harvesting, threshing, and sales.

- Harvest, storage, produce sales, buyer records, sales proceeds, and distribution of proceeds.
- Management dashboard and investor portal.
- Notifications for upcoming tasks, funding approvals, missing receipts, overspending, delayed

#### activities, and investor updates.

- Document and evidence storage for proposals, farm plans, activity logs, receipts, photos,

#### agreements, and reports.

### 4.2 Out of Scope for MVP

- IoT sensors, drone mapping, satellite imagery, and automated crop disease detection unless added

#### in a later phase.

- In-platform collection, escrow, wallet, or disbursement of investor funds; MVP will track off-platform

#### transfers and proofs only.

- Regulated public investment marketplace features until legal and compliance requirements are

#### confirmed.

- Government subsidy, insurance, tax, and formal accounting integrations beyond exportable reports.
- Guaranteed yield or investment return predictions; the system should show scenarios and actuals,

#### not promise outcomes.

- Marketplace logistics and delivery management beyond recording buyers, sales, and proceeds.

## 5. Stakeholders and User Groups

##### User group Primary needs

```
Farm owner Create farms, approve plans, monitor performance, invite managers and investors, review
profit/loss.
```
```
Farm organization admin Manage the tenant account, users, farms, roles, investor access, subscription, and
organization-wide reporting.
```
```
Farm manager / farmer Record activities, request funds, upload evidence, manage tasks, track inputs, report issues
and harvests.
```
```
Investor View approved fund usage, project status, activity evidence, expected returns, capital recovery,
profit share, and formal reports.
```
```
Operations/log keeper Maintain diaries, enter historical records, organize photos, and clean up activity data.
```
```
Finance/admin Approve expenses, reconcile receipts, track budget, record sales, calculate distributions.
```
```
Agronomist/advisor Review production plans, risks, pest/disease or animal-health notes, recommended actions,
and activity history.
```
```
Buyer/customer Optional future user who can be recorded against sales, commodity commitments, payments,
and delivery notes.
```

##### User group Primary needs

```
Platform administrator Manage subscriptions, tenant accounts, roles, configuration, support, and system-level audit
logs.
```
## 6. Target Business Process

#### 1. Farm organization creates a tenant account, then creates farm profiles with business name,

#### location, farm contacts, production units, farm seasons, and user roles.

#### 2. Farmer creates a production plan with units, commodities, timeline, required inputs, expected

#### activities, estimated costs, expected outputs, and risk assumptions.

#### 3. Investor reviews the proposal, agrees to a capital-first configurable profit-share model, and

#### transfers funds externally in full or by approved phases.

#### 4. Farm manager logs daily or weekly activities through mobile web or WhatsApp with date,

#### production unit, commodity, activity type, cost, labour, inputs used, remarks, next activity, and

#### photo or receipt evidence.

#### 5. The system compares actual spend to budget and flags variances, missing receipts, delayed tasks,

#### and activities that require approval.

#### 6. Investor receives an approved transparency feed showing what was done, what approved spend

#### was recorded, what evidence was attached, and what remains in each funding phase.

#### 7. Harvest/output and sales are recorded with quantity, buyer, price, deductions, proceeds, and

#### commodity movement.

#### 8. The system calculates capital recovery, remaining unrecovered capital, profit/loss, and profit-share

#### distribution according to the investment agreement.

#### 9. Season-end report is generated for farm owner, farmer, investors, and management review.

## 7. Functional Requirements

### 7.1 Farm, Production Unit, and Production-Cycle Management

##### ID Priority Business requirement

```
FR-FRM-01 Must Allow organizations to create farms with business name, owner, farm type, location, contact
details, notes, registration details, and farm photos.
```
```
FR-FRM-02 Must Allow farms to contain plots, fields, pens, ponds, houses, or other production units with name,
size/capacity, suitability, status, and optional map/GPS references.
```
```
FR-FRM-03 Must Allow users to create production cycles by farm, unit, season, farm type, commodity, start date,
expected output/harvest date, and production method.
```
```
FR-FRM-04 Must Support all farm types through configurable templates, including crop, livestock, poultry,
aquaculture, mixed farms, and plantations.
```
```
FR-FRM-05 Must Support mixed cultivation and intercropping, including maize with pepper and eggplant on the
same plot.
```
```
FR-FRM-06 Should Allow plan changes with reason, date, approver, impact on budget, impact on expected output,
and investor notification.
```

##### ID Priority Business requirement

```
FR-FRM-07 Should Maintain a season or production timeline showing planned, completed, delayed, skipped, and
changed activities.
```
### 7.2 Farm Diary, Journal, and Evidence Capture

##### ID Priority Business requirement

```
FR-DIA-01 Must Provide a farm diary where users can log crop activities such as clearing, harrowing, planting,
spraying, weeding, harvesting, and sales, as well as farm-type activities such as feeding,
vaccination, stocking, water-quality checks, breeding, and mortality records.
```
```
FR-DIA-02 Must Each diary entry must support date, farm, production unit, production cycle, commodity, activity
type, description, inputs used, labour used, cost, remarks, next activity, and status.
```
```
FR-DIA-03 Must Allow photo, video, receipt, invoice, and document attachments to be linked to diary entries.
```
```
FR-DIA-04 Must Allow activity entries to be tagged as planned, in progress, completed, delayed, blocked, or
changed.
```
```
FR-DIA-05 Must Support WhatsApp-based updates so farmers can submit or forward activity notes and photos
through WhatsApp, with admin review before they become official records.
```
```
FR-DIA-06 Should Support voice-to-text or quick mobile entry for farmers who prefer sending short field updates.
```
```
FR-DIA-07 Should Allow investor-safe summaries, where sensitive internal notes can be separated from updates
visible to investors.
```
### 7.3 Budget, Funding, and Expense Management

##### ID Priority Business requirement

```
FR-FIN-01 Must Create a farm-season or production-cycle budget with categories such as tools, seeds/stock,
land preparation, herbicides, planting, labour, feeding, health care, fertilizers, pest control,
harvest/output handling, transport, and contingency.
```
```
FR-FIN-02 Must Support phased funding with budgets, requested amount, approved amount, externally
transferred/released amount, spent amount, balance, status, and supporting evidence.
```
```
FR-FIN-03 Must Record expenses against activity, phase, category, vendor, payment method, amount, receipt,
approver, and whether the expense is investor-visible.
```
```
FR-FIN-04 Must Show budget versus actual spend at farm, production cycle, production unit, phase, and
category level.
```
```
FR-FIN-05 Must Track off-platform money movement using transfer proof, bank reference, payer/payee, amount,
date, status, and reconciliation note; Farmwell must not process or hold funds in MVP.
```
```
FR-FIN-06 Must Allow role- and threshold-based approval of fund release requests, major expenses, budget
overruns, and plan changes.
```
```
FR-FIN-07 Should Support carry-forward balances between phases, such as a Phase 1 budget balance moving
into Phase 2.
```
```
FR-FIN-08 Should Support configurable currency, with NGN as the initial default.
```
### 7.4 Investor Portal and Investment Agreements


##### ID Priority Business requirement

```
FR-INV-01 Must Allow investors to view only the farms, seasons, agreements, updates, and financial records
they are permitted to access.
```
```
FR-INV-02 Must Capture investment agreement terms including investor, farm, season, amount committed,
amount funded, capital recovery rule, profit split, role responsibilities, and notes.
```
```
FR-INV-03 Must Support capital-first recovery, where sales proceeds first recover investor capital before profit
sharing is calculated.
```
```
FR-INV-04 Must Support configurable profit-sharing formulas, including examples such as 40/60 and 30/70 after
capital recovery.
```
```
FR-INV-05 Must Display fund usage by phase, including externally released funds, approved expenses, balance,
approved evidence, and completion status.
```
```
FR-INV-06 Must Use approved expense-level transparency as the default investor visibility model: investors see
category, amount, status, receipt/photo evidence where approved, and summaries for their own
investment.
```
```
FR-INV-07 Must Hide internal private notes, unrelated farm records, unapproved drafts, sensitive bank/personal
details, and records belonging to other investors or farms.
```
```
FR-INV-08 Should Provide investor progress reports showing planned versus actual spend, field activity evidence,
farm production status, risks, expected output, sales, and projected returns.
```
```
FR-INV-09 Should Allow investor comments or questions on updates, expenses, and milestone completion.
```
### 7.5 Approval and Governance Workflow

##### ID Priority Business requirement

```
FR-APP-01 Must Allow each farm organization to configure approval thresholds by amount, category, phase, farm
type, and investment agreement.
```
```
FR-APP-02 Must Require investor approval for funding releases, material budget overruns, major plan changes,
and any expense category marked investor-approval-required.
```
```
FR-APP-03 Must Allow farm owner/admin approval for routine day-to-day expenses that are within approved
budget and below configured threshold.
```
```
FR-APP-04 Must Allow finance/admin users to reconcile external transfer proofs, receipts, sales proceeds, and
payout records.
```
```
FR-APP-05 Must Require investor review or acknowledgement before final profit distribution is marked complete.
```
```
FR-APP-06 Should Allow approval delegation, escalation, and clarification requests so farm work is not blocked by
unavailable approvers.
```
### 7.6 Inputs, Inventory, and Assets

##### ID Priority Business requirement

###### FR-INVTRY-

###### 01

```
Must Track farm inputs such as seeds, herbicides, pre-emergence chemicals, fertilizers, insecticides,
fungicides, foliar spray, nets, sacks, and fuel.
```
```
FR-INVTRY-
02
```
```
Must Track tools and equipment such as hoes, cutlasses, knapsack sprayers, pumps, hoses, nets,
and machinery hire.
```
```
FR-INVTRY-
03
```
```
Must Record purchase quantity, unit cost, total cost, supplier, purchase date, storage location, and
linked receipt.
```

##### ID Priority Business requirement

###### FR-INVTRY-

###### 04

```
Should Reduce stock when inputs are used in an activity entry and show remaining stock balance.
```
###### FR-INVTRY-

###### 05

```
Could Support low-stock alerts and reorder suggestions based on upcoming planned activities.
```
### 7.7 Task Calendar and Reminders

##### ID Priority Business requirement

```
FR-TASK-01 Must Generate a farm task calendar from the production plan and allow manual tasks to be added.
```
```
FR-TASK-02 Must Support reminders for time-sensitive activities such as planting windows, transplanting, fertilizer
application, spraying intervals, feeding schedules, vaccination, pond checks, weeding, and
harvest/output readiness.
```
```
FR-TASK-03 Should Flag delayed tasks and require a reason when a planned activity is moved, cancelled, or
replaced.
```
```
FR-TASK-04 Should Show next activity on the farm dashboard and investor progress view.
```
### 7.8 Risk, Issues, and Decision Logs

##### ID Priority Business requirement

```
FR-RISK-01 Must Allow users to record operational risks such as birds, rodents, pests, rainfall delay, input price
increase, labour shortage, theft, disease, flooding, market price changes, and buyer default.
```
```
FR-RISK-02 Must Link plan changes to risk reasons, such as changing Farm 2 from rice-first to maize-first
because neighbouring farms planted maize and rice would face bird/rodent pressure alone.
```
```
FR-RISK-03 Should Allow each risk to have severity, probability, owner, mitigation, due date, status, and impact on
budget or timeline.
```
```
FR-RISK-04 Should Notify investors when a material change affects the production plan, spending, timeline, or
expected return.
```
### 7.9 Harvest, Sales, and Distribution

##### ID Priority Business requirement

```
FR-HAR-01 Must Record harvest or production-output events by farm type, commodity, production unit, date,
quantity, unit, quality notes, labour cost, and evidence.
```
```
FR-HAR-02 Must Record commodity or production-output sales with buyer, quantity, unit price, gross amount,
deductions, payment status, and receipt/payment evidence.
```
```
FR-HAR-03 Must Calculate capital recovered, unrecovered capital, gross profit, net profit, and profit-share
distribution after sales are recorded.
```
```
FR-HAR-04 Should Support recurring harvest crops such as pepper and eggplant with weekly harvest entries over
several months.
```
```
FR-HAR-05 Should Support different harvest stages for crops such as rice: cutting, gathering, threshing, bagging,
and sale.
```
### 7.10 Reports and Dashboards


##### ID Priority Business requirement

```
FR-RPT-01 Must Provide a farm owner dashboard with farm status, production cycles, current phase, activities,
budget, actual spend, balance, harvest/output status, and expected returns.
```
```
FR-RPT-02 Must Provide an investor dashboard with committed capital, released capital, spent funds, remaining
balance, evidence feed, capital recovery status, and expected profit share.
```
```
FR-RPT-03 Must Generate farm activity reports by date range, farm, commodity, production unit, phase, and
activity type.
```
```
FR-RPT-04 Must Generate financial reports for budget, actual spend, variance, fund release, harvest revenue,
sales, profit/loss, and distribution.
```
```
FR-RPT-05 Must Generate formal investor reports for a selected period, farm, agreement, or funding phase.
```
```
FR-RPT-06 Should Export reports to PDF and Excel for investor review, accounting, and offline sharing.
```
```
FR-RPT-07 Could Generate automatic weekly investor summaries using diary entries, photos, expenses, and
upcoming activities.
```
## 8. Reporting Requirements

##### Report Purpose Key fields

```
Farm activity log Replace spreadsheet/manual logs with a
searchable operational record.
```
```
Date, farm, production unit, commodity, activity,
input, cost, remark, next activity, evidence.
```
```
Funding phase report Show staged capital release and milestone
completion.
```
```
Phase, budget, approved, externally released,
spent, balance, status, evidence, approver.
```
```
Budget variance report Expose where actual spending differs from
plan.
```
```
Category, budget, actual, variance, reason, linked
activity.
```
```
Investor fund usage report Give investors approved expense-level
visibility into how their money was used.
```
```
Investor, agreement, external funds released,
approved expense categories, receipts, photos,
balance.
```
```
Production progress report Show planned versus actual production-cycle
progress.
```
```
Commodity, production unit, planned date, actual
date, status, delay reason, next step.
```
```
Risk and issue report Track material risks and plan changes. Risk, severity, probability, mitigation, owner,
status, investor notification.
```
```
Harvest and sales report Track output and revenue by commodity. Commodity, quantity, unit, buyer, price, gross
sales, deductions, net sales.
```
```
Profit distribution report Calculate investor and farmer returns. Capital invested, capital recovered, net profit, split
formula, payable amounts.
```
```
Formal investor statement Provide a shareable report suitable for
investor review and recordkeeping.
```
```
Opening balance, funds released, approved
spend, evidence summary, harvest/output, sales,
capital recovery, profit share, closing position.
```
## 9. Initial Data Model

##### Data object Minimum required fields

```
Tenant / organization Business name, CAC/reg details, subscription plan, owner, users, roles, settings, farms.
```
```
Farm Name, owner, business/reg details, farm type, location, contacts, description, photos, status.
```

##### Data object Minimum required fields

```
Production unit Farm, type, name, size/capacity, notes, production history, current status, GPS/map reference.
```
```
Production cycle Farm, production unit, season, commodity, planned dates, actual dates, budget, status,
expected output.
```
```
Activity diary Date, activity type, production unit, production cycle, description, inputs, labour, cost, remark,
next activity, evidence.
```
```
Budget Farm, season, category, planned amount, phase, notes, assumptions, approval status.
```
```
Expense Date, category, activity, amount, vendor, payment method, receipt, approver, investor visibility.
```
```
Funding phase Phase name, budget, requested, externally released, spent, balance, status, milestone,
evidence.
```
```
Investor agreement Investor, farm/season, amount, funding model, capital recovery rule, profit split, start/end,
status.
```
```
Approval Request type, requester, approver, threshold, status, comment, approved amount, timestamp.
```
```
Harvest/output Date, commodity, production unit, quantity, unit, quality, labour cost, storage/sale status.
```
```
Sale Buyer, commodity, quantity, price, gross, deductions, payment status, proof, linked
harvest/output.
```
```
Distribution Agreement, capital recovered, net profit, split formula, investor share, farmer share, payout
status.
```
```
Evidence File type, file, caption, date, uploader, linked activity/expense/harvest, visibility.
```
## 10. Non-Functional Requirements

##### ID Priority Business requirement

```
NFR-01 Must The web app must be mobile-first because farm updates are most likely captured from phones in
the field.
```
```
NFR-02 Must The system must support low-bandwidth usage, compressed media uploads, and reliable saving
of field records.
```
```
NFR-03 Must The system must enforce role-based access so investors cannot see unrelated farms or private
internal records.
```
```
NFR-04 Must Every financial, approval, agreement, and activity-status change must be auditable with user,
timestamp, old value, new value, and reason where applicable.
```
```
NFR-05 Must Sensitive investor, financial, identity, and farm-location data must be protected through HTTPS,
secure authentication, database access controls, and backups.
```
```
NFR-06 Should The platform should support offline-friendly drafts or resilient retry for rural field conditions,
especially for diary entries and media uploads.
```
```
NFR-07 Should The system should support English first, with Hausa or other Nigerian language support
considered later if farmer adoption requires it.
```
```
NFR-08 Should The interface should make investor views clear and simple, avoiding technical farm jargon
where summary language is more useful.
```
```
NFR-09 Must The application must support multi-tenant separation so one farm organization cannot access
another organization's records.
```
```
NFR-10 Could The architecture should allow future native mobile apps, compliant payment/wallet integrations,
geotagging, marketplace, insurance, and advisory integrations after legal review.
```

## 11. Integrations

##### Integration Need MVP position

```
Email Account setup, investor invitations, reports,
password reset, weekly summaries.
```
```
Recommended for MVP.
```
```
WhatsApp Farmers should be able to submit or forward
updates and photos through WhatsApp, and
investors may receive update summaries.
```
```
Required capability; MVP can start with
WhatsApp Business/API-assisted intake or an
admin-reviewed forwarding workflow.
```
```
Cloud storage/media Photos, videos, receipts, agreements, and farm
evidence must be stored securely.
```
```
Required for MVP.
```
```
Payments/bank transfer Investor funding and payouts are tracked as
external transfers with evidence and
reconciliation notes.
```
```
Tracking only in MVP; no in-platform money
collection, escrow, wallet, or disbursement.
```
```
Weather API Weather-based reminders and rainfall context
for planting/spraying decisions.
```
```
Phase 2 unless easy to add.
```
```
Maps/GPS Farm/plot location, geotagged evidence, and
field boundary mapping.
```
```
Basic GPS optional in MVP; mapping later.
```
```
Accounting/export Export financial records for tax/accounting or
external bookkeeping.
```
```
CSV/XLSX/PDF export in MVP.
```
```
AI summary Turn diary entries and photos into
plain-language investor updates.
```
```
Optional phase 2/3 feature.
```
## 12. Key Business Rules

- A farm season must have at least one production cycle before expenses, activities, and

#### harvest/output can be fully reported.

- Each funding phase should have a planned budget and milestone definition before funds are

#### requested.

- Investors should see approved released funds, approved spend, balance, evidence, and status for

#### their own investment only.

- Investors should not see internal private notes, unapproved drafts, unrelated farms, sensitive

#### bank/personal details, or records belonging to other investors.

- Routine in-budget expenses should be approved by farm owner/admin or finance/admin; investors

#### should approve fund releases, material overruns, major plan changes, threshold-defined expenses,

#### and final profit distribution.

- Plan changes should capture the reason and impact before being shown as an approved new plan.
- Capital-first agreements should allocate sales proceeds to capital recovery before profit-share

#### calculations.

- Profit sharing must be configurable per agreement; examples include 40/60 and 30/70 after capital

#### recovery.

- Photos and receipts should be linked to the exact activity, expense, harvest, or sale they support.


- Deleted or edited financial records must remain auditable; the system should not silently erase

#### history.

## 13. MVP Recommendation

##### Phase Recommended scope Outcome

```
Phase 1: SaaS operating
record
```
```
Multi-tenant account setup,
farm/production-unit setup, production-cycle
setup, diary entries, activity log, photo/receipt
uploads, budget categories, expenses, task
calendar, WhatsApp update intake, and basic
dashboard.
```
```
Farmwell replaces WhatsApp-only updates and
spreadsheet-only activity tracking with one
reliable operating record across different farm
types.
```
```
Phase 2: Investor
transparency
```
```
Investor accounts, agreements, phased
funding, role/threshold approvals, fund usage
dashboard, budget variance, formal investor
reports, capital recovery and configurable
profit-share calculations.
```
```
Investors can see approved fund usage and
understand how returns are calculated without
micromanaging routine farm work.
```
```
Phase 3: Growth and
intelligence
```
```
AI progress reports, richer WhatsApp
automation, weather reminders, geotagged
evidence, mobile app/offline mode,
marketplace/buyer records, advisor workflows.
```
```
Farmwell becomes scalable for many farm
organizations, investors, and agribusiness
partners.
```
## 14. Acceptance Criteria

- A farm organization can create Farmwell tenant records, farms, production units, production cycles,

#### budgets, and user roles.

- A farmer can record an activity with date, production unit, commodity, cost, remark, next activity, and

#### photo/receipt evidence from a mobile device.

- The system can reproduce an activity log similar to the provided 2026 wet-season log, including

#### phase totals, spent amount, and balance.

- A farm manager can request phase funding and attach milestone evidence.
- An investor can view approved released funds, approved actual spend, balance, receipts/photos,

#### production progress, and next planned activity.

- A farmer can submit a field update through WhatsApp, and an authorized admin can review and

#### convert it into an official activity record.

- A plan change can be logged with reason, impact, and investor-visible explanation.
- Harvest/output and sales can be recorded by commodity, quantity, buyer, amount, and evidence.
- The system can calculate capital recovery and profit sharing from sales proceeds based on the

#### configured agreement.

- Management can export farm activity, budget, investor, harvest/output, sales, and distribution

#### reports.

- The system can generate a formal investor report for a selected period or funding phase.
- Financial edits, approvals, and deletion attempts are captured in an audit log.


## 15. Assumptions and Open Questions

##### Area Question / assumption to validate

```
Brand and legal Confirm official product naming, CAC details, logo, address, and whether the platform should
expose FARMWELL AGTECH SOLUTIONS as the operating company.
```
```
Pilot farm Confirm whether MDSxclusive Farms should be the first tenant/client record inside Farmwell
and whether its historical records should be imported.
```
```
Legal/compliance Because Farmwell is for the broader market, confirm whether it will merely manage existing
private farm-investor relationships or publicly advertise investment opportunities.
```
```
Approval thresholds Confirm default naira thresholds and categories that require investor approval versus farm
owner/admin approval.
```
```
WhatsApp implementation Confirm whether MVP should use WhatsApp Business API immediately or begin with an
admin-reviewed manual forwarding/import workflow.
```
```
Offline use Confirm if the farmer often works in low-connectivity areas and needs offline drafts or a mobile
app in MVP.
```
```
Roles Confirm whether the farmer, farm owner, investor, and finance approver can be the same
person on small family farms.
```
```
Formal reports Confirm required investor report format, branding, sign-off fields, and whether reports should
be exportable as PDF, Excel, or both.
```
```
Data import Confirm whether existing Excel, Word, PDF, and WhatsApp records should be imported into
Farmwell for the pilot season.
```
## 16. Source References

- Activity Log.xlsx: MDSxclusive Farms 2026 Wet Season Activity Log.
- MDSxclusive Farms (2026 Plan).doc: 2026 farming season running cost and implementation plan.
- Farm_Investment_Proposal_v1.pdf: capital-first farm investment proposal with phased funding.
- WhatsApp messages dated April 5-10, 2026 describing clearing, burning, harrowing/tilling, planting

#### readiness, Farm 2 strategy change, and requested profit-share adjustment.

- Supplied photos showing field clearing, burning, harrowing/tilling, Farm 2, and pepper/eggplant

#### nursery under netting.

## 17. Sign-Off

##### Role Name Signature Date

```
Product sponsor
```
```
Farm operator
```
```
Investor representative
```
```
Finance/admin lead
```
```
Implementation partner
```

