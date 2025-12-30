# EPIC — MPIT Budget Engine v2 (FINAL)
## Source-Driven • Baseline+Forecast • Allowance Caps • Exception-Only Variances (NO accounting ledger)

> Scope: **complete refactor**, destructive (dev-only), **no backward compatibility** with V1 data or flows.  
> Target: replace V1 (Baseline Expense + Budget Amendments + portfolio buckets + Custom recurrence 1..12) with a simpler, stable system centered on Contracts, Projects, Cost Centers, and annual Budgets.

---

## 0) Hard rules (non-negotiable)

### 🔒 0.1 No assumptions
- **Do not assume** field names, option values, file paths, or semantics not explicitly confirmed by repo evidence or by this EPIC text.
- If a detail is missing, **STOP** at the relevant GATE and report.

### 🧨 0.2 “Delete = Delete”
- If this EPIC says “remove/delete”, you must **physically delete** code + fixtures + docs + reports + workspace items + tests + devtools + chart sources.
- Forbidden alternatives:
  - renaming to `_old`
  - moving to “deprecated”
  - commenting out and leaving artifacts around

### 🧾 0.3 Repo-only work (no web for the agent)
- Agent must use repo evidence only (grep/open files), no internet browsing.

### 🧰 0.4 Frappe/ERPNext version lock
- Keep the current Frappe/ERPNext major line in use (repo evidence indicates v15).
- Implement using standard Frappe patterns:
  - DocType controller hooks (`validate`, etc.)
  - DocField meta properties (`depends_on`, `mandatory_depends_on`, `read_only_depends_on`)
  - Tree DocType / Nested Set model + optional `{doctype}_tree.js`
  - Script/Query Reports + Dashboard Chart Source
  - `bench migrate` workflow (patches + schema + fixtures + dashboards + translations)

---

## 1) Background and intent

### 1.1 What you (vCIO) need the product to do
- Insert **Contracts** (recurring + price changes) and **Projects** (stimates → quotes → deltas).
- Create the **annual Budget** automatically from those sources.
- Add a few **Cost Center caps/allowances** manually in the Budget (e.g., “IT Internal = 250€/month”).
- During the year, **do not register everything**: register only:
  - allowance spending (absolute spends that consume caps)
  - contract/project deltas or savings (exceptions)
- Contract changes (e.g., ISP 49→69 from 01/08) are managed in the contract itself; the budget refresh recalculates automatically.
- Transparent variance views: Baseline vs Forecast vs Variance/Spend; by month, Cost Center, Category, Vendor, per contract/project line.

### 1.2 Why V1 must be removed (not patched)
V1 is amendment-centric and baseline-import-centric (Baseline Expense + Amendments + portfolio bucket logic). This creates drift and complexity against the “source-driven + exceptions only” workflow.

This EPIC explicitly:
- **removes** Baseline Expense
- **removes** Budget Amendment (and related ADR/docs/charts/reports)
- **removes** the “Custom recurrence 1..12 months” concept and replaces it with a clearer Contract spread/accrual model

---

## 2) Key decisions (LOCKED)

### 2.1 Planning and comparison model
- **Baseline Budget** = approved snapshot (immutable) for the year.
- **Forecast Budget** = working/current plan, refreshable from sources.
- Reports must support:
  - Baseline vs Forecast (plan changes)
  - Forecast vs Variance/Spend (operational control)
  - Baseline vs Variance/Spend (accountability)

### 2.2 Variances vs “Actuals”
- The system is **exception-only**:
  - For contracts/projects, planned consumption is automatic; record only **deltas** (extra costs or savings) as **Variance/Exception Entries**.
  - For cost-center caps, record **absolute spends** that consume the allowance.
- Implementation detail:
  - Keep DocType name `MPIT Actual Entry` to reduce rename risk, but **reframe it in UI/docs** as “Variance/Exception Entry”.

### 2.3 Contract accrual across years (spread)
- Multi-year prepaid contracts must be **spread across months through multiple years**.
- V1’s `custom_period_months` (1..12) is removed.
- New fields on Contract: `spread_months` (unbounded) + `spread_start_date`.
- Spread lives on **Contract** (not on rate schedule).

### 2.4 Overlap counting
- Period overlap counts **calendar months touched**, not only full months.

### 2.5 Rate schedule for contract price changes
- Contract supports rate schedule segments (`effective_from`).
- `effective_to` is implicit: day before next effective_from.
- No overlapping segments (hard fail). Gaps allowed and mean “no planned charge in that gap”.

### 2.6 Cost Center caps (Allowance lines)
- No separate “Planned Expense” DocType.
- Allowances are **manual Budget Lines** (`line_kind=Allowance`) per Cost Center (often monthly).
- “Remaining allowance” is computed, never stored:
  - cap (monthly/YTD) − allowance spend entries (monthly/YTD)

### 2.7 Category mandatory for reporting
- Keep `category` mandatory where it already is (especially on variance/spend entries).

### 2.8 Locked open questions (explicitly not reopened)
- overlap = “months touched”
- net is master; gross selectable in views
- caps are allowance budget lines (manual per year)
- variance entries exist; full actual ledger not required

### 2.9 Locked clarifications (new)
- Budget/Forecast lifecycle: one Baseline per year (`budget_kind="Baseline"` unique); Baseline immutable and refresh is forbidden unless `budget_kind="Forecast"`; multiple Forecast per year allowed but only one active per year (`is_active_forecast=1`). “Set Active” server action sets the chosen Forecast active and deactivates others for that year; only vCIO Manager can activate/refresh. Historical Forecasts become read-only (no edits to generated lines or key fields).
- Projects monthly distribution (LOCKED): distribute project totals uniformly across the calendar months touched by `[planned_start_date .. planned_end_date]`; if either date is missing, default interval is the entire budget year (uniform over 12 months).
- Actual Entry inclusion (LOCKED): only entries with `status = "Verified"` are included in calculations/reports; `Recorded` is excluded.
- Delta linkage rule (LOCKED): for `entry_kind = "Delta"`, enforce XOR: either contract OR project is set, never both.
- Allowance Spend rule (LOCKED): for `entry_kind = "Allowance Spend"`, require `cost_center` and forbid contract and project links.
- Project quote statuses (LOCKED): statuses are `Approved` and `Informational` (case-sensitive); only `Approved` contributes to `quoted_total`.
- Active Forecast selection (LOCKED): a budget is “current” iff `budget_kind = "Forecast"` and `is_active_forecast = 1` (unique per year); reports fall back to Baseline if no active Forecast exists.
- Refresh rounding (LOCKED): spread accrual uses 2-decimal rounding; months 1..N-1 = round(total/N, 2); last month = total − sum(previous); sum of months always equals total.
- Rate schedule gaps (LOCKED): gaps generate no lines (planned=0 in those months).
- Project dates validation (LOCKED): if both planned dates empty → fallback to whole budget year (12 months). If only one set → error. If `planned_end_date < planned_start_date` → error. Distribution is uniform on months touched.
- Actual Entry status/editing (LOCKED): statuses remain Recorded/Verified; only Verified counts. Once Verified, entry is read-only; reverting to Recorded allowed only for vCIO Manager. Negative amounts allowed for Allowance Spend; if negative, description is mandatory.
- Quote workflow (LOCKED): Select field, default Informational; only vCIO Manager can set Approved (no workflow).
- Cost Center tree (LOCKED): seed root node “All Cost Centers” (is_group=1, no parent) via fixtures/after_install.
- UI defaults (LOCKED): Actual Entry form defaults status=Recorded; entry_kind defaults to Delta if contract/project present else Allowance Spend; cost_center always visible, mandatory_depends_on for Allowance Spend. Budget form shows year, budget_kind, is_active_forecast, baseline_ref; Forecast buttons: Refresh from Sources, Set Active, Create Baseline Snapshot; Baseline has no refresh. List views: no forced hidden filters; provide saved filters like Verified Only/Recorded Drafts; sorting Actual Entry by posting_date desc, Budget by year desc then budget_kind. Reports: default to active Forecast and Verified entries; period default to selected year. Dashboard chart “Plan Delta” breakdown by Category with optional Cost Center filter; default year = selected/current. Labels avoid “Actual”, use “Exceptions/Variances” and “Allowance Spend”.
- Devtools/tests (LOCKED): seed at least Cost Center root; tests create their own data but smoke/bootstrap should provision the root and ensure an MPIT Year exists for the current year if required.

---

## 3) Deliverables and acceptance criteria

### 3.1 Deliverables
1) Data model updated (DocTypes + fields + constraints)  
2) New Cost Center tree DocType  
3) Contract enhancements:
   - `spread_months` + `spread_start_date`
   - rate schedule (effective_from segments) for price changes
4) Budget refresh engine (idempotent upsert from sources)
5) Variance/Spend engine (exception-only semantics)
6) Reports rewritten (no amendments, no portfolio bucket logic)
7) Overview dashboard chart source replaced (same purpose, new data source)
8) ADR updates:
   - ADR 0003 and ADR 0009 marked **Superseded** and link to new ADR
   - New ADR describing V2 model
9) Docs updated end-to-end (tutorials, references, import templates)
10) Tests updated/added; devtools verify/bootstrap updated
11) Repo-wide grep cleanup (must hit zero)

### 3.2 Acceptance criteria (functional)
- Creating contracts + projects + allowances, then **Generate/Refresh Forecast**, yields a complete annual plan.
- Changing a contract (ISP 49→69 from 01/08) updates Forecast after refresh:
  - Jan–Jul use 49, Aug–Dec use 69 (VAT handling consistent with net master).
- Prepaid contract spread (e.g., 3000 over 36 months) contributes a consistent monthly accrual across year boundaries.
- A variance entry recorded for a project increases/decreases the project’s expected totals and therefore the Forecast plan after refresh.
- Allowance spends reduce remaining allowance for that Cost Center; remaining is visible by month and YTD.
- Baseline remains unchanged; Forecast can change; reports show both comparisons.

### 3.3 Acceptance criteria (repo-level / “done means done”)
- Must-hit-zero grep list (section 12) passes.
- Workspace does not expose removed doctypes.
- Dashboard does not reference removed chart sources.
- Tests and devtools do not hard-fail due to removed doctypes.

---

## 4) Known current repo surfaces that MUST be addressed (from dump_llm evidence)
> This list is not optional. It is evidence-backed from repo greps and file opens.

### 4.1 MPIT Actual Entry surfaces
- DocType JSON: `mpit_actual_entry.json` (amount not reqd; has VAT fields; has status; has category required; has optional links vendor/contract/project/budget/budget_line_ref).
- Python controller: `mpit_actual_entry.py` (`validate()` derives year from posting_date, computes VAT split).
- JS: `mpit_actual_entry.js` applies VAT defaults using `mpit_user_prefs.get_vat_defaults`.
- API: `mpit_user_prefs.py` provides whitelisted VAT defaults endpoint; `mpit_project.py` has whitelisted `get_project_actuals_totals(project)`.
- Reports querying `tabMPIT Actual Entry`:
  - `mpit_current_budget_vs_actual.py`
  - `mpit_projects_planned_vs_actual.py`
  - `mpit_monthly_budget_vs_actual.py`
  - `mpit_approved_budget_vs_actual.py`
- Patch referencing MPIT Actual Entry VAT fields: `backfill_vat_fields.py` (also mentions V1 doctypes).
- Workspace link: `master_plan_it.json` includes “Actual Entries” -> MPIT Actual Entry.
- Docs mention: `10-money-vat-annualization.md`, `08-user-guide.md`
- Tests mention:
  - `test_mpit_actual_entry.py` (derives year)
  - `test_smoke.py` checks doctypes exist

✅ These must be updated for V2 semantics (exception-only).

---

## 5) Mandatory checkpoints (STOP & REPORT)
> The agent must complete these checkpoints **before** implementing later steps.

### GATE 0 — Pre-flight blast radius & semantics freeze (read-only)
#### GATE 0.A — Repo grep mapping
- Run recursive grep (rg not available; log it if missing) for:
  - `MPIT Baseline Expense`
  - `MPIT Budget Amendment`
  - `MPIT Amendment Line`
  - `is_portfolio_bucket`
  - `source_baseline_expense`
  - `custom_period_months`
  - `recurrence_rule` + `Custom`
- Output:
  - a table: `match_string -> file_path -> action(delete/update) -> step_owner`

#### GATE 0.B — Schema freeze
- Open and extract field lists (file path + snippet) for:
  - MPIT Budget, MPIT Budget Line, MPIT Contract, MPIT Project
  - Project Allocation, Project Quote
  - MPIT Settings (to detect V1-only settings that must be removed)
  - MPIT Actual Entry
- Output:
  - `docs/v2_migration/data_model_freeze.md`
  - Must include explicit decisions for any missing-but-required dimension (e.g., whether Budget Line already has cost_center, vendor, etc).

#### GATE 0.C — Logic freeze
- Identify and snippet:
  - annualization/overlap logic (months touched)
  - any amount computation engine used by budget lines
  - Budget controller logic that may be amendment-centric
  - report queries that use V1 fields (`is_portfolio_bucket`, amendments, baseline imports)
- Output:
  - `docs/v2_migration/logic_freeze.md`

❌ If any of these cannot be completed with repo evidence: STOP.

### GATE 1 — “Define Project monthly distribution model”
The repo + docs must define how project totals appear in monthly view.
- Options must be explicitly selected and documented before implementation:
  - uniform spread across months of the year
  - date-range based allocation (requires fields)
  - one-off (requires month/date)
- Output:
  - `docs/v2_migration/project_monthly_distribution.md`
- Implementation cannot proceed without this decision.

---

## 6) Repo-wide change plan (basic-agent safe, follow in order)

### STEP 0 — Pre-flight (read-only)
- Complete GATE 0.
- Create `docs/v2_migration/impact_map.md` with:
  - To Delete / To Rewrite / To Keep / Needs decision
- Create `docs/v2_migration/semantics.md`:
  - Definitions: Baseline, Forecast, Allowance, Exception Entry, Delta, Allowance Spend

---

### STEP 1 — Remove Baseline Expense (V1) completely
**Delete Doctype and test artifacts**
- Delete the Baseline Expense DocType directory (as found by GATE 0).
- Remove any references in:
  - Contract doctype (field `source_baseline_expense`)
  - Budget Line doctype (field `baseline_expense`)
  - devtools baseline import scripts
  - docs baseline import template/tutorial
  - tests and verify gates
  - patches (e.g., any patch file listing Baseline Expense, including backfill files)

**Update verification gates**
- Update devtools verify/bootstrap scripts so they do not require Baseline Expense.
- Update smoke tests so they do not require Baseline Expense.

**Update docs/templates**
- Remove baseline import how-to + templates (delete files if present).
- Update tutorials removing baseline import steps.

✅ Acceptance: grep for `MPIT Baseline Expense` must be zero by the end (STEP 12).

---

### STEP 2 — Remove Budget Amendments (V1) completely
**Delete Doctype and tests**
- Delete amendment doctypes directories discovered by GATE 0.
- Delete dashboard chart source that aggregates amendments (directory discovered by GATE 0).

**Update ADRs (supersede correctly)**
- ADR 0003 and ADR 0009: add at top:
  - `Status: Superseded by ADR 0011` + link
- Create ADR 0011:
  - V2 model definition: Baseline vs Forecast + exception entries + allowance lines + spread + rate schedule rules
  - rationale: why replacing amendments
  - destructive dev note

**Update workspace / desk surface**
- Remove menu shortcuts to amendments/baseline imports (if present).
- Remove dashboard references to amendment chart sources.

**Update tests & devtools**
- Remove or rewrite amendment tests.
- Update devtools scripts that create amendment charts or rely on amendment doctypes.

✅ Acceptance: grep for amendment terms must be zero by end (STEP 12).

---

### STEP 3 — Introduce Cost Center (new) and wire it everywhere
**New DocType**
- Create `MPIT Cost Center` as a Tree DocType.
- Implement as NestedSet model (same pattern as MPIT Category) and enable tree view.

**Add Link fields**
Add `cost_center` link to:
- `MPIT Contract`
- `MPIT Project`
- `MPIT Budget Line`
- `MPIT Actual Entry` (variance/spend entries)

**Autopopulation**
- Prefer DocField `fetch_from` + `fetch_if_empty` for copying cost_center from contract/project into entries and budget lines when appropriate.
- JS should not be the only enforcement.

✅ Acceptance:
- Tree view works.
- Cost center is available as reporting dimension.

---

### STEP 4 — Contract: add spread (accrual) and formalize rate schedule for price changes
**Remove old Custom recurrence**
- Remove `custom_period_months` from all doctypes and any validation/annualization code.
- Remove recurrence rule `"Custom"` entirely.

**Add Contract spread fields**
- Add:
  - `spread_months` (Int, unbounded)
  - `spread_start_date` (Date)
  - optional computed `spread_end_date`

**Mutual exclusion**
- If `spread_months` set:
  - rate schedule must be empty
- If rate schedule has rows:
  - spread fields must be empty

**Rate schedule validation**
- Sort by `effective_from`.
- No overlap allowed (hard fail).
- Gaps allowed → “no planned charge”.

✅ Acceptance:
- Contract controller rejects overlap.
- Spread supports multi-year crossing.
- No `custom_period_months` remains in repo.

---

### STEP 5 — Budget Line simplification and Allowance lines
**Goal**: one master amount input + derived totals; explicit line kind; stable keys.

**Remove V1 ambiguous fields** (only after GATE 0 confirms which exist)
- Remove:
  - `monthly_amount`
  - `annual_amount`
  - `is_portfolio_bucket`
  - redundant fields that are strictly derivable (keep minimal computed set required by reports)

**Add required V2 fields**
- Add:
  - `line_kind`: Contract | Project | Allowance | Manual
  - `source_key` (read-only): stable key to upsert generated lines
  - `is_generated` (Check)
  - `is_active` (Check) if not already present

**Allowance line semantics**
- Allowance lines are manual entries in the Budget:
  - typically Monthly recurrence
  - amount is net master; gross view-only

✅ Acceptance:
- Allowance lines are not modified by refresh.
- Generated lines cannot be manually edited (enforce server-side; optionally UI read-only).

---

### STEP 6 — Budget refresh engine (Forecast generation) with idempotent upsert
Implement server method on Budget (whitelisted) “Refresh from Sources”.

**Inputs**
- budget year
- refresh mode: generated only (do not touch manual/allowance)

**Contracts**
- For each contract relevant to the year:
  - If contract is spread:
    - monthly accrual = total / spread_months
    - generate planned monthly contributions for months in year touched by overlap
  - Else if contract has rate schedule:
    - create segments per rate row ∩ year
  - Else normal recurrence:
    - generate plan line(s) per recurrence rules

**Projects**
- Use project expected_total and the Project monthly distribution model defined in GATE 1.
- Allocations must be category-granular (STEP 8 for project schema work).

**Upsert key rules**
- `source_key` must be unique within a budget and deterministic:
  - Contract spread: `CONTRACT_SPREAD::<contract_name>`
  - Contract rate: `CONTRACT_RATE::<contract_name>::<effective_from>`
  - Contract flat: `CONTRACT::<contract_name>`
  - Project: `PROJECT::<project_name>::<year>::<category>` (or variant confirmed by schema freeze)

**Idempotence**
- Re-running refresh:
  - updates existing generated lines matching source_key
  - adds missing ones
  - deactivates generated lines whose source no longer applies (**do not delete**)

✅ Acceptance:
- Refresh can be run multiple times with no duplicates.
- Totals remain stable for unchanged sources.

---

### STEP 7 — Variance/Spend entries (exception-only) using MPIT Actual Entry
**Reframe the concept**
- Keep DocType name `MPIT Actual Entry`, but rename labels in UI/docs to “Variance/Exception Entry”.

**Minimal schema changes**
- Add `entry_kind`:
  - `Delta`
  - `Allowance Spend`

**Rules**
- Delta entries:
  - must link to either contract or project (strictly enforce; define whether both at once are allowed; default: forbid both)
  - represent ± adjustment (positive extra cost, negative savings)
  - must have category (already mandatory)
- Allowance Spend:
  - must have cost_center
  - consumes allowance
  - must not link contract/project

**Status semantics**
- Preserve existing status values unless schema freeze decides otherwise.
- Define which status counts in totals (e.g., only Verified).

**Project integration**
- Project expected totals must include:
  - allocations/quotes + sum(approved deltas)
- Approved deltas must be mapped to a deterministic condition (e.g., status=Verified) and documented in `docs/v2_migration/semantics.md`.

✅ Acceptance:
- It is impossible to create ambiguous entries.
- Reports clearly show “Exceptions / Allowance Spend” (not “actual ledger”).

---

### STEP 8 — Projects: stima by category → quotes → deltas/savings
**Allocations**
- Make project allocations category-granular (add category link and enforce required).

**Quotes**
- Quotes must be category-granular.
- Quotes status must support: Approved vs Informational.

**Expected totals logic**
- planned_total = sum allocations
- quoted_total = sum approved quotes
- expected_total = (quoted_total if any else planned_total) + sum approved deltas/savings

✅ Acceptance:
- Refresh uses expected_total.
- Reports show planned vs quoted vs expected.

---

### STEP 9 — Dashboard chart source replacement (same purpose, new source)
Replace amendment delta chart with a chart that shows “Plan Delta”:
- Purpose remains: show change between Baseline and Forecast by category (optionally cost center).
- New chart source should:
  - read Baseline budget lines vs Forecast budget lines
  - compute delta (Forecast - Baseline)

✅ Acceptance:
- Dashboard no longer references amendment chart source.
- Chart reflects baseline vs forecast deltas.

---

### STEP 10 — Reports rewrite (no amendments, no portfolio logic)
Rewrite these reports to V2 semantics (and rename labels accordingly):
- `mpit_current_budget_vs_actual`:
  - Current = active Forecast; fallback to Baseline if no Forecast
  - Replace “actual” meaning with exceptions/spend (MPIT Actual Entry)
- `mpit_approved_budget_vs_actual`:
  - Baseline view
- `mpit_monthly_budget_vs_actual`:
  - monthly planned = sum monthly contributions across lines (spread + rate segments + contract recurrence + project model)
  - monthly exceptions = Delta entries (approved)
  - monthly allowance spend = Allowance Spend entries
- `mpit_projects_planned_vs_actual`:
  - rename and rewrite semantics: planned vs expected vs deltas (not “actual ledger”)

Remove:
- any dependence on amendments
- any `is_portfolio_bucket` logic

✅ Acceptance:
- Reports run without removed doctypes/fields.
- Reports do not treat MPIT Actual Entry as accounting ledger; they show exceptions/spend.

---

### STEP 11 — Tests & devtools alignment (must not hard-fail)
**Tests**
- Delete amendment/baseline tests.
- Update smoke test to require only V2 doctypes.
- Add minimal deterministic tests:
  1) overlap “months touched”
  2) spread 36 months across years
  3) rate schedule segments (49→69 from 01/08)
  4) refresh idempotence
  5) allowance spend reduces remaining allowance
  6) MPIT Actual Entry constraints (delta vs allowance spend)

**Devtools**
- Remove baseline import scripts and amendment chart builders.
- Ensure bootstrap/verify match V2.

✅ Acceptance:
- `bench migrate` and test suite pass on a clean dev site.

---

## 7) Risks and pitfalls (what basic agents usually get wrong)
- Leaving surface area drift (workspace links, chart sources, docs, templates, verify gates).
- Keeping “Actual Entry” name but not updating labels/docs (wrong mental model).
- Idempotence failures (duplicate budget lines).
- Spread vs rate schedule mixing (unclear math) — must be mutually exclusive.
- Monthly report math: do not revert to annual/12 blindly.
- ADR hygiene: old ADRs must be superseded, not deleted.

---

## 8) Must-hit-zero grep list (anti-drift)
At end of work, repo must not contain:
- `MPIT Baseline Expense`
- `MPIT Budget Amendment`
- `MPIT Amendment Line`
- `is_portfolio_bucket`
- `source_baseline_expense`
- `custom_period_months`
- recurrence rule `Custom`

Additionally, if these strings appear in GATE 0 results, they must also be removed or rewritten:
- any “include_portfolio” filter/label (if present)
- any V1-only settings/labels discovered in MPIT Settings

---

## 9) DoD checklist (copy/paste)
- [ ] GATE 0 complete (impact map + schema freeze + logic freeze)
- [ ] GATE 1 complete (project monthly distribution decision + docs)
- [ ] Baseline Expense removed (doctype + docs + templates + devtools + tests)
- [ ] Budget Amendment removed (doctype + docs + chart source + reports + tests)
- [ ] ADR 0003 + 0009 marked Superseded + link to ADR 0011
- [ ] ADR 0011 created (V2 model)
- [ ] Cost Center tree implemented and used
- [ ] Contract supports:
  - [ ] spread_months + spread_start_date (unbounded)
  - [ ] rate schedule segments (effective_from) with no overlap
- [ ] Budget Line simplified; Allowance line_kind implemented
- [ ] Budget Forecast refresh idempotent (source_key)
- [ ] Variance/Exception entries implemented (delta + allowance spend)
- [ ] All main reports rewritten (no amendments, no portfolio buckets)
- [ ] Overview chart replaced (same purpose, new data source)
- [ ] Workspace cleaned (no removed doctypes visible)
- [ ] Repo grep must hit zero (section 8)
- [ ] Tests and verify gates pass

---

## Appendix A — Frappe v15 reference links (for implementation patterns)
> These are official references (use them to justify implementation choices; do not invent new patterns).

```text
Controllers (DocType hooks like validate/after_insert/etc):
https://docs.frappe.io/framework/user/en/basics/doctypes/controllers

Document API (insert triggers validate/before_insert/on_update/after_insert):
https://docs.frappe.io/framework/user/en/api/document

DocField meta (depends_on, mandatory_depends_on, read_only_depends_on):
https://docs.frappe.io/framework/user/en/basics/doctypes/docfield

Fetch From / Fetch If Empty (field autopopulation patterns):
https://docs.frappe.io/framework/user/en/guides/app-development/fetch-custom-field-value-from-master-to-all-related-transactions

Tree API (doctype_tree.js customization) + Desk Tree view support:
https://docs.frappe.io/framework/user/en/api/tree
https://docs.frappe.io/framework/user/en/desk

Script Report:
https://docs.frappe.io/framework/user/en/desk/reports/script-report

bench migrate (patches + schema + fixtures + dashboards + translations sync):
https://docs.frappe.io/framework/user/en/bench/reference/migrate
