# Permissions and Visibility

Applies through: Phase 6 - Harvest, Sales, Capital Recovery
Last reviewed: 2026-06-11  
Audience: Support users, owners, admins, and implementation maintainers  
Related app areas: Team Settings, Dashboard, Farms, Finance, Harvests, Investors, Approvals, Investor Portal

Use this guide to understand why a user can or cannot see a Farmwell workspace or record.

## Current Roles

| Platform role | Main access | Notes |
| --- | --- | --- |
| Owner | Full team access | Can manage team, settings, farm operations, finance, investor agreements, approval requests, and deletion. |
| Admin | Broad operational access | Can update team details, invite/cancel invitations, manage settings, farm operations, finance, investor agreements, and approval requests. |
| Member | Farm operations visibility | Can view farm operations. No finance or investor administration by default. |
| Investor | Investor portal only | Can view the investor portal for their own active or completed agreements and approved scoped records. |

The BRS includes additional user groups such as farm manager, operations/log keeper, finance/admin, agronomist/advisor, buyer/customer, and platform administrator. Through Phase 6, these map onto the current team roles above rather than separate app roles.

## Permission Summary

| Permission area | Owner | Admin | Member | Investor |
| --- | --- | --- | --- | --- |
| Team update | Yes | Yes | No | No |
| Team deletion | Yes | No | No | No |
| Invitations | Yes | Yes | No | No |
| Team settings | Yes | Yes | No | No |
| Audit events | Yes | No by default | No | No |
| Farm operations | Yes | Yes | View only | No |
| Finance | Yes | Yes | No | No |
| Harvests | Yes | Yes | View only when farm operations access exists | No |
| Investor agreements | Yes | Yes | No | No |
| Approval requests | Yes | Yes | No | No |
| Investor portal | Yes by full permission set | No by default | No | Yes |

Always confirm the current team before investigating access. The same user may belong to multiple teams with different roles.

## Workspace Access

- Dashboard requires current-team access and is intended for internal summary review.
- Farms, Field Diary, Tasks, and WhatsApp Intake require farm operations access.
- Finance pages require finance access.
- Harvests can be viewed by users with farm operations access or finance access.
- Recording harvests requires farm operations management or finance management.
- Recording sales and recalculating recovery/distributions requires finance management.
- Investors and Approvals require investor agreement or approval-request access.
- Investor Portal requires investor-portal access and an agreement-scoped record set.
- Team Settings require team membership and the relevant team-management permission.

## Investor Visibility Statuses

Investor-linked records can use these visibility states:

- Private: internal only.
- Pending approval: waiting for review.
- Approved: eligible for investor portal visibility if linked to the investor's agreement.
- Rejected: not approved for investor visibility.
- Clarification requested: waiting for more information before approval.

Approved status alone is not enough. The record must also belong to the investor's own agreement and current team.

## Agreement Scope

Investor access is narrowed by agreement.

A record should appear to an investor only when:

- The investor belongs to the current team.
- The investor has an active or completed agreement.
- The record is linked to that agreement.
- The record is approved for investor visibility.
- The record does not include internal notes or sensitive private information.

Unrelated farms, unrelated investors, inactive agreements, private records, and unapproved records should remain hidden.

Harvests, sales, and distributions follow the same agreement scope. A harvest must be linked to the investor's agreement and approved for visibility. A sale is visible only when it belongs to the agreement and is approved directly or through an approved distribution record. A distribution is visible only when it belongs to the agreement and has approved investor visibility after acknowledgement.

## Evidence Visibility

Evidence files are private.

Use the correct authorized download route:

- Farm activity evidence uses farm evidence access.
- Harvest evidence uses farm evidence access.
- Finance receipts and transfer proof use finance evidence access.
- Sale evidence uses finance evidence access internally and investor evidence access when approved for the investor.
- Investor-approved evidence uses investor evidence access.
- Agreement documents are exposed only through agreement-scoped authorization.

Do not share raw storage paths.

## Troubleshooting Access

When a user cannot see a workspace:

- Confirm they are on the correct current team.
- Confirm their team role.
- Confirm the role includes the needed permission.
- Confirm the page is implemented through Phase 6.
- Confirm generated navigation is permission-aware for that user.

When an investor cannot see a record:

- Confirm the investor is a team member with Investor role.
- Confirm the agreement is active or completed.
- Confirm the record is linked to that agreement.
- Confirm the record visibility is approved.
- For sales, confirm either the sale is approved or the linked distribution is approved.
- For distributions, confirm the investor has acknowledged or approved the distribution request.
- Confirm the record is not private, rejected, or clarification-requested.
- Confirm the record belongs to the same team.

When an internal user cannot create or update finance or investor records:

- Confirm they are Owner or Admin.
- Confirm related farm, cycle, category, expense, transfer, or agreement records belong to the same team.
- Confirm the workflow is implemented through Phase 6.
- Confirm validation is not rejecting a cross-team reference.

When an internal user cannot create harvest or sale records:

- Confirm harvest creation is being attempted by a user with farm operations management or finance management.
- Confirm sale creation is being attempted by a user with finance management.
- Confirm the referenced harvest, farm, cycle, unit, commodity, and investor agreement belong to the current team.
- Confirm the sale is linked to an existing harvest record.

## Current Limits

This guide does not define separate permissions for platform administrator, agronomist/advisor, buyer/customer, AI, reports, exports, or backups because those workflows are not current through Phase 6.
