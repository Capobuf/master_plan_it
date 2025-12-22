# Tutorial: First budget cycle (end-to-end)

This tutorial walks through the V1 lifecycle with native Desk UI.

## 0) Preconditions
- You have a dev site (tenant) with `master_plan_it` installed
- You have user accounts for:
  - vCIO Manager
  - Client Editor
  - Client Viewer

## 1) Setup the year
- Create `MPIT Year` for the target year (e.g. 2026).
- Mark it active if needed.

## 2) Setup categories and vendors
- Create a category tree in `MPIT Category` (groups + leaf categories).
- Create vendors in `MPIT Vendor`.

## 3) Import baseline historical spend
- Use Data Import to import into `MPIT Baseline Expense`.
- Use comments on each expense to ask clarifications.
- Update `status` to `Validated` once clarified.

## 4) Create contracts/subscriptions
- For recurring items, create `MPIT Contract` records.
- Set `next_renewal_date` (operational) and `notice_days`.
- Attach contract documents where available.

## 5) Draft the annual budget
- Create `MPIT Budget` for the year.
- Add `MPIT Budget Line` entries:
  - map from baseline and contracts where appropriate
  - add your compensation as a line
  - add a “portfolio bucket” line (is_portfolio_bucket=1)

## 6) Review and approve (workflow)
- Client Editor and vCIO Manager can move workflow states.
- When approved, the Budget is submitted and becomes immutable.

## 7) Record actuals during the year
- Insert `MPIT Actual Entry` records.
- Category is mandatory.
- Link to Contract or Project when relevant.

## 8) Amendments for post-approval changes
- Create `MPIT Budget Amendment` linked to the approved Budget.
- Add delta lines (+/-).
- Approve the amendment via workflow.

## 9) Projects (multi-year)
- Create `MPIT Project`.
- Add yearly allocations (mandatory for project approval).
- Add quotes and milestones as needed.
- Link project to budget lines and/or actual entries.

## 10) Reporting & dashboards
- Use the provided reports/dashboards:
  - Approved vs Actual
  - Current vs Actual
  - Renewals window
  - Projects planned vs actual

