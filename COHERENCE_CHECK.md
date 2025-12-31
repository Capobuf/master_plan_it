# Coherence and scope check (V1)

Date: 2025-12-21

This document captures the *consistency review* of the V1 design before implementation.
It is written to prevent drift and over-engineering.

## What we are building (V1)

**Master Plan IT** is a Frappe Desk app used by a vCIO and the client’s users to manage:
- Historical spend baseline (raw → clarified → validated)
- Annual budget proposal → approval (immutable baseline)
- In-year changes via amendments (delta-based)
- Actuals (consuntivi) with warnings (no blocking)
- Contracts/subscriptions with renewals visibility
- Projects with multi-year allocations

## Hard constraints

- **Desk only** (clients are System Users). No portal/Website Users.
- **Native components only**: DocType, Workflow, Reports, Dashboards, Data Import, Calendar/Kanban/List views.
- No custom JS/CSS; no asset build.
- 1 client = 1 Frappe **site** (tenant). The vCIO operates across sites.

## Forbidden Duplicate Metadata Paths

Canonical metadata lives under `master_plan_it/master_plan_it/`. If any of these paths exist, it is drift and must be removed:
- `master_plan_it/master_plan_it/doctype/`
- `master_plan_it/master_plan_it/report/`
- `master_plan_it/master_plan_it/workflow/`
- `master_plan_it/master_plan_it/workspace/`
- `master_plan_it/master_plan_it/dashboard/`
- `master_plan_it/master_plan_it/dashboard_chart/`
- `master_plan_it/master_plan_it/number_card/`
- `master_plan_it/master_plan_it/master_plan_it_dashboard/`
- `master_plan_it/master_plan_it/print_format/`

## Linearity & no-contradictions audit

### Data separation
- Multi-site means each tenant has its own DB; cross-customer access is not supported by design.
- vCIO user accounts must exist on each site (unless an external SSO is introduced later).

### Baseline vs Contracts
- **Baseline Expense** is the “inbox” for historical charges (often messy).
- **Contract** is the curated, durable record for renewals/scadenze. Baseline can link to Contract, but not required.
This avoids conflating raw imports with governance objects.

### Budget immutability
- The **Approved budget never changes**.
- Any change after approval is a **Budget Amendment** with *delta lines* (positive/negative).
This enables two consistent comparisons:
1) Approved vs Actual (baseline start-of-year)
2) Current (Approved + Amendments) vs Actual (rolling reality)

### Projects multi-year
- Projects can span years; therefore V1 **requires per-year allocations** before a project can be approved.
This prevents ambiguous annual reporting.

### Actuals linkage
- Actual entries must always have a **Category** (minimum indispensable).
- They can additionally link to a **Contract** and/or **Project**.
- Directly linking to a child-table Budget Line is not reliable in Frappe; we keep only a soft reference in V1.

### Renewals visibility
- Renewals are driven by `MPIT Contract.next_renewal_date` (explicit, operational).
- Calendar/Kanban/List + saved filters + renewals report provide visibility with native Desk tools.
- Optional: scheduled reminders via `scheduler_events` (native backend), but not required for V1 correctness.

## Over-engineering check

Included in V1 because it is required by your stated workflow:
- Contracts with renewals
- Amendments for post-approval changes
- Project allocations per year

Deferred (V2) because it increases complexity without blocking V1:
- SSO across sites
- Automatic “copy baseline → budget” wizard in UI (V1 can use a CLI script)
- Complex contract proration/annualization rules (V1 uses explicit dates/amounts + reports)

Result: **V1 is minimal yet complete** for the described budgeting lifecycle.
