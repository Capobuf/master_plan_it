# MPIT Budget Engine v2 — Logic, decisions, rationale (working notes)

This document consolidates the decisions and reasoning that led to the V2 design. It is intentionally explicit and narrative (“why”, trade-offs, and how it matches the vCIO workflow).

---

## 1) The problem we’re solving

You need a budgeting tool for IT governance (vCIO):
- Minimal data entry.
- Clear monthly + yearly visibility.
- Granular drilldown: contract/project/cost center/category/vendor.
- Ability to adjust plans as reality changes, without turning the system into a full accounting ledger.

The key constraint: **you do not want to record every transaction**. You want to record:
- *only* exceptions (extra costs or savings) on contracts/projects
- *only* real spends that consume “cap” allowances for cost centers

So V2 is not “accounting actuals”. It is an **operational variance tool** that keeps the plan coherent and auditable with minimal input.

---

## 2) Why V1 must be replaced (not patched)

V1 is built around:
- Baseline Expenses imports (historical spend as a first-class object)
- Budget Amendments (immutable baseline + deltas)
- Portfolio bucket flags

This structure conflicts with your operating model:
- You want the plan to be “defined” by contracts/projects + a few caps, not by imported baseline lines.
- You want changes to come from source edits (contract rate change; refined project quotes), not a separate amendments ledger.
- You want the monthly view to be meaningful for multi-year prepaid costs, not constrained to “custom months inside the year”.

Because V1 assumptions are spread across docs, dashboard chart sources, reports, devtools verification, templates, and tests, it is safer to **remove** the V1 concepts and rebuild V2 consistently than to keep compatibility and accumulate drift.

---

## 3) Core mental model (V2)

### 3.1 Entities a user works with
- **Contract**: recurring services or prepaid commitments, sometimes with price changes.
- **Project**: planned initiative with initial stima and later refinement (quotes), plus deltas/savings during execution.
- **Cost Center**: an organizational container for caps/allowances and reporting (tree).
- **Budget (Year)**:
  - Baseline (approved snapshot)
  - Forecast (current working plan)
- **Variance/Exception Entry** (technical name may remain “Actual Entry”):
  - Delta for contract/project
  - Allowance Spend for cap consumption

### 3.2 What the user does in practice
At the beginning of the year:
1) Insert contracts:
   - ISP 49€/month + VAT
   - vCIO fee 750€/month
   - Antivirus 3000 total for 36 months (prepaid)
2) Insert projects:
   - “Ground floor refit” stima 6000 (by categories)
3) Create baseline budget and add allowance caps:
   - IT Internal: 250€/month cap
   - IT Company: X €/month cap
4) Clients approve baseline.

During the year:
- For allowance spends (HDD, monitor): record an Allowance Spend entry.
- For project or contract deviations: record deltas or update sources:
  - Contract changes: update contract rate schedule (e.g., 69 from 01/08)
  - Project refinement: update quotes or add approved deltas/savings

At any time:
- Forecast refresh recalculates the plan automatically.
- Reports show Baseline vs Forecast vs variance/spend, monthly and YTD.

---

## 4) Baseline vs Forecast instead of Amendments (and why)

### Decision
Remove Budget Amendments entirely and replace them with:
- **Baseline Budget**: approved snapshot
- **Forecast Budget**: refreshable working plan

### Why this is better for your workflow
- Fewer concepts in UI.
- Changes come from the objects that actually changed:
  - contract schedule changes
  - project cost refinement
  - approved deltas/savings
- Still supports audit and transparency:
  - baseline is immutable
  - forecast is traceable (optionally via revisions, but not required to start)

### How we keep ADR hygiene
V1 ADRs must not be silently deleted; they become historical:
- ADR 0003 and ADR 0009 are marked “Superseded by ADR 0011”, with a link from old to new.

---

## 5) Exception-only “Actuals”: can we eliminate “actual” as a concept?

### Decision
Eliminate “actual ledger” concept. Keep only a **Variance/Exception Entry** concept.

### Meaning
- Contract/Project planned consumption is automatic.
- You record **only deviations**:
  - positive = extra costs
  - negative = savings
- Allowance spend entries are absolute and reduce remaining caps.

This gives you operational control without forcing you to mirror accounting.

---

## 6) Contract multi-year accrual (“antivirus 3 years”)

### Constraint discovered in codebase
V1 uses `custom_period_months` with documentation and validation constraining it to **1..12**, meaning “within the year”.

### Decision
Remove `custom_period_months` and “Custom recurrence” entirely and introduce a clearer concept on Contract:
- `spread_months` (unbounded)
- `spread_start_date`

### Rationale
- Prevents semantic overload: “Custom” can’t simultaneously mean “billing cycle” and “accrual term”.
- Matches the widely used ERP concept of deferred expense by months (uniform monthly accrual).

### Output
The budget plan for each year is simply the monthly accrual multiplied by the number of months overlapping that year.

---

## 7) Contract price changes (ISP upgrade mid-year)

### Decision
Use a rate schedule table on Contract:
- each row: effective_from + pricing fields
- effective_to implicit from next row

Rules:
- no overlaps (error)
- gaps allowed (no planned charge during gap)

Rationale:
- Aligns with how real contracts change: a new effective date with a new monthly fee.
- Keeps changes in the source, enabling automatic budget recalculation.

---

## 8) Projects: stima by category → quotes → deltas/savings

### Goal
Allow projects to start as rough estimates, then become detailed as quotes arrive, and still support exception-only recording for extras/savings.

### Decision
- Project allocations become category-granular.
- Project quotes are category-granular and can be “approved” or “informational”.
- Add a project change log for approved deltas/savings (by category).

### Expected totals logic
- planned_total = sum allocations
- quoted_total = sum approved quotes
- expected_total = (quoted_total if any else planned_total) + sum approved changes

Budget refresh consumes expected_total to keep the forecast aligned.

---

## 9) Cost Centers and allowance caps (“buckets”)

### Decision
No separate Planned Expense entity.
Caps are implemented as **manual Allowance Budget Lines** per Cost Center in each year.

### Semantics
- Allowance is net by default; gross is view-only.
- Remaining allowance is computed:
  - cap (monthly/YTD) − allowance spend entries (monthly/YTD)

This yields the “I have 250€/month; HDD and monitor are consuming it” experience you want.

---

## 10) Reporting requirements (the non-negotiables)

Reports and dashboard must work without amendments and without “portfolio bucket” flags.

Key views:
- Monthly plan vs variance/spend (per cost center/category)
- Baseline vs Forecast (how the plan changed)
- Contract-level view (with rate changes and spread)
- Project view (planned vs quoted vs expected vs deltas/savings)
- Allowance remaining (per cost center)

The existing “Monthly Plan vs Exceptions” report must be rewritten to:
- include contract rate segments correctly
- include spread contracts as monthly accruals across years
- stop using annual/12 blindly
- stop using `is_portfolio_bucket`

---

## 11) Repo hygiene and “agent-proof” rules

### Superseded ADRs
- Old ADRs stay but are flagged superseded and link to the new ADR.
- New ADR explains the V2 model.

### Dashboard chart source replacement
- The “amendments delta” chart is replaced by a chart with the same purpose:
  - “Plan Delta” between Baseline and Forecast.
- Same user intent; different data source.

### Must-hit-zero grep list (anti-drift)
At end of work, repo must not contain:
- MPIT Baseline Expense
- MPIT Budget Amendment
- MPIT Amendment Line
- is_portfolio_bucket
- source_baseline_expense
- custom_period_months
- recurrence rule "Custom"

---

## 12) Open questions (explicitly NOT reopened unless you ask)
These were previously discussed and are considered locked for V2.0:
- overlap = “months touched”
- net is master; gross selectable in views
- caps are implemented as allowance budget lines (manual per year)
- variance entries exist; full actual ledger is not required
