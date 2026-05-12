# MPIT Budget Model Refactor — Agent Execution Plan

## 0. Role and operating rules

You are the operational agent for the `master_plan_it` Frappe v16 app.

Act as a senior Frappe developer and senior UI/UX designer. Implement only clean, maintainable changes. Do not hide uncertainty. If a rule conflicts with the existing code or with Frappe framework behavior, stop and report the conflict before changing data semantics.

Mandatory rules:

- Do not invent business rules.
- Do not assume missing behavior.
- Prefer native Frappe Framework v16 features: DocType JSON, controller methods, Script Reports, hooks, fixtures, scheduler events, patches, `.po` translations.
- Do not introduce a custom Desk Page, custom SPA, CSS hacks, or report-side workarounds unless explicitly approved.
- Keep the economic logic server-side. Client scripts are for UX feedback only.
- Add comments in English for non-obvious code, especially where the economic/management rationale is encoded.
- Every user-facing string added in Python must use `_()` with literal strings.
- Every user-facing string added in JavaScript must use `__()` with literal strings.
- Keep field labels/descriptions in DocType JSON translatable and update the Frappe translation files.
- Do not delete historical economic records to “fix” totals. Use explicit states (`Replaced`, `Cancelled`, `Closed`) and deterministic idempotent sync.
- Verify with tests and migration checks before declaring completion.

Local documentation to read before editing:

- `AGENT_INSTRUCTIONS.md`
- `CLAUDE.md`
- `docs/explanation/01-architecture.md`
- `docs/reference/03-workflows.md`
- `docs/reference/04-reports-dashboards.md`
- `docs/reference/10-money-vat-annualization.md`
- `docs/reference/12-i18n.md`
- `docs/ux/field-help-text.md`
- Existing tests under `master_plan_it/tests/` and DocType-level `test_*.py`

Official Frappe documentation checked for this plan:

- Controllers and controller hooks: https://docs.frappe.io/framework/user/en/basics/doctypes/controllers
- DocType event execution: https://docs.frappe.io/framework/user/en/guides/app-development/executing-code-on-doctype-events
- Script Reports: https://docs.frappe.io/framework/user/en/desk/reports/script-report
- Hooks, `doc_events`, and scheduler events: https://docs.frappe.io/framework/user/en/python-api/hooks
- Translations: https://docs.frappe.io/framework/user/en/translations
- Database migrations and patches: https://docs.frappe.io/framework/user/en/database-migrations
- Database transaction behavior: https://docs.frappe.io/framework/user/en/api/database
- Form scripts: https://docs.frappe.io/framework/user/en/api/form
- Field types: https://docs.frappe.io/framework/user/en/basics/doctypes/fieldtypes

Relevant Frappe directives to apply:

- Controller hooks such as `before_validate`, `validate`, `before_save`, `after_insert`, `on_update`, and `on_change` belong in the DocType controller. `validate` is for blocking invalid saves; document creation derived from saved data must be idempotent and should not be hidden in client code.
- `on_change` can be called by `db_set`; any logic there must be idempotent. Avoid using `on_change` for the contract-to-expense sync unless there is a specific reason.
- Scheduler events belong in `hooks.py`; after changing scheduler hooks, run migration so the scheduler registration is applied.
- Script Reports should return `columns`, `data`, and optional `report_summary`/chart data from Python; avoid reimplementing economic calculations in JS.
- Frappe extracts DocType JSON labels/descriptions automatically. Source-code strings must be literal strings passed to `_()` or `__()`; do not pass variables, f-strings, interpolated template literals, or concatenated strings into the translation functions.
- For translated strings with variables, use positional placeholders such as `{0}` and format after translation.
- Data patches must expose `execute()` and be listed in `patches.txt`. If a patch depends on new schema, use a post-model-sync patch or explicitly `frappe.reload_doc(...)` before reading/writing new fields.
- Uncaught exceptions in patches and background/scheduled jobs roll back the transaction. Do not swallow exceptions that indicate data corruption.

---

## 1. Product decisions already approved

These are final for this implementation.

### 1.1 Project statuses

User-facing Italian target:

- `Idea`
- `Proposto`
- `Approvato`
- `Rimandato`
- `Rifiutato`

Recommended canonical stored values for Frappe i18n consistency:

- `Idea`
- `Proposed`
- `Approved`
- `Deferred`
- `Rejected`

Reason: the current app already uses English source strings and Italian translations via `.po`. Keeping source/canonical values in English reduces future translation debt and aligns with Frappe extraction. The Italian UI must show the approved Italian labels via translations.

If you decide to store Italian option values directly in the database, stop and report before implementation. Do not silently mix English and Italian stored status values.

Approved legacy mapping if using recommended canonical values:

| Old stored value | New stored value | Italian UI |
|---|---:|---|
| `Open` | `Idea` | `Idea` |
| `On Hold` | `Idea` | `Idea` |
| `Completed` | `Approved` | `Approvato` |
| `Cancelled` | `Deferred` | `Rimandato` |

For legacy `Cancelled -> Deferred`, set `deferred_to_year = current year + 1`.

If `MPIT Year` for `current year + 1` does not exist, the migration must fail explicitly. Do not create the year automatically.

### 1.2 Deferred projects

- `deferred_to_year` is mandatory when project status is `Deferred` / `Rimandato`.
- A daily scheduler job must promote projects from `Deferred` to `Proposed` when the current year matches `deferred_to_year`.
- This is a real state transition, not only report logic.
- The job must be idempotent.
- The job must not touch `Rejected`, `Approved`, `Idea`, or already `Proposed` projects.

### 1.3 Contract status

Replace the existing contract status model completely.

User-facing Italian target:

- `Attivo`
- `Concluso`

Recommended canonical stored values:

- `Active`
- `Concluded`

Rules:

- `Active`: at least one contract term has empty `to_date`, or `to_date >= today`.
- `Concluded`: all contract terms have a non-empty `to_date`, and the maximum `to_date < today`.
- The status is calculated by the system and must not be manually editable.
- `MPIT Contract` must always have at least one child `MPIT Contract Term` row.

Remove or adapt legacy logic based on:

- `Draft`
- `Pending Renewal`
- `Renewed`
- `Cancelled`
- `Expired`
- `VALID_CONTRACT_STATUSES`
- `ACTIVE_CONTRACT_STATUSES`

### 1.4 Contract term auto-renewal

- Automatic.
- Runs on contract save.
- No checkbox for “Generate Expense”.
- No button for “Create Actual for Current Year”.
- Existing `auto_renew` still controls renewal of contract terms, not expense generation.
- Never create duplicate or overlapping terms.
- Never create an infinite sequence on repeated saves.

Recommended no-loop rule:

- On save, if `auto_renew = 1`, ensure the contract terms cover the planning horizon represented by existing `MPIT Year` records.
- Do not create terms beyond the latest existing `MPIT Year.end_date`.
- If there are no existing `MPIT Year` records, do not auto-create years and do not create future terms only to satisfy an absent planning horizon.
- Successor terms start the day after the previous term’s `to_date`.
- Successor terms copy amount, VAT flag/rate, billing cycle, and relevant note/attachment only if this is already consistent with current data design.
- If the latest term has empty `to_date`, do not create a successor term.

### 1.5 Contract-generated expenses

- Generated automatically after saving the contract.
- Generated only for existing `MPIT Year` records.
- Do not create missing `MPIT Year` records.
- Generated expenses use `workflow_state = Closed` because they are effective costs derived from contracts.
- Generated rows use `row_phase = Actual`.
- The sync must be idempotent.
- Stable `external_reference` is mandatory.
- The contract remains a context/generator, not an independently summed economic source.

Core economic rule:

```text
Official budget totals must derive from MPIT Expense rows only.
MPIT Contract generates MPIT Expense rows.
MPIT Project classifies and governs inclusion/exclusion.
Do not sum contracts independently into official budget totals.
```

### 1.6 Expense row replacement

- When an active row points to `replaces_row_name`, the target row must automatically become `Replaced`.
- The replaced target must belong to the same `MPIT Expense` document.
- A row cannot replace itself.
- Replacement cycles are forbidden.
- Nothing can replace an `Actual` row.
- `Estimate` and `Quote` rows can be replaced freely, including same-phase replacement.
- Examples allowed:
  - `Estimate -> Estimate`
  - `Estimate -> Quote`
  - `Estimate -> Actual`
  - `Quote -> Quote`
  - `Quote -> Estimate`
  - `Quote -> Actual`
- Examples forbidden:
  - `Actual -> anything`
  - self-replacement
  - cyclic replacement
  - replacement of a row in another expense

Implementation note:

```python
# A replacement is a lifecycle correction, not a deletion.
# The old row remains auditable, but it no longer contributes to budget totals.
```

### 1.7 Plafond

- Keep the current code behavior: only one non-cancelled `Plafond` expense per year and cost center.
- Ignore the PDF directive that would allow multiple plafonds for the same year + cost center.
- A spending expense may consume a plafond belonging to another cost center. This is approved and already mostly aligned with the current model.

### 1.8 Vendor

Official supplier/vendor source:

- `MPIT Expense Row.vendor`

Not official:

- `MPIT Expense.vendor`

Implementation rule:

- Do not use `MPIT Expense.vendor` for reports, filters, financial engine calculations, or budget grouping.
- Keep `MPIT Expense.vendor` temporarily only as a deprecated migration/backward-compatibility field if removing it in the same release would create avoidable regressions.
- Hide or clearly de-emphasize the parent vendor field in the form.
- Migrate existing data by copying parent vendor into empty child row vendor values.
- For contract-generated expenses: `MPIT Expense Row.vendor = MPIT Contract.vendor`.
- For ordinary non-plafond rows, validate that `row.vendor` is present.
- For plafond rows, vendor may remain optional unless current business data proves it must be mandatory.

---

## 2. Current code reality to respect

Static inspection found these relevant existing files and behaviors:

- `master_plan_it/master_plan_it/doctype/mpit_project/mpit_project.json`
  - Current `workflow_state` options: `Open`, `On Hold`, `Completed`, `Cancelled`.
- `master_plan_it/master_plan_it/doctype/mpit_project/mpit_project.py`
  - `VALID_PROJECT_STATUSES = {"Open", "On Hold", "Completed", "Cancelled"}`.
- `master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.json`
  - Current `status` options: `Draft`, `Active`, `Pending Renewal`, `Renewed`, `Cancelled`, `Expired`.
- `master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.py`
  - `VALID_CONTRACT_STATUSES = {"Active", "Pending Renewal", "Renewed"}`.
  - Current manual function: `create_actual_from_contract(...)`.
  - Current generated expense state is `Open`; must become `Closed`.
- `master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.js`
  - Adds the `Create Actual for Current Year` button; remove this UX.
- `master_plan_it/master_plan_it/doctype/mpit_expense/mpit_expense.py`
  - Already validates one non-cancelled plafond per year/cost center.
  - Already allows cross-cost-center plafond reference by validating only same year and plafond kind/state.
  - Current replacement validation does not automatically mark targets as `Replaced` and does not forbid replacing `Actual` rows.
- `master_plan_it/master_plan_it/financial_engine.py`
  - Currently has `ACTIVE_CONTRACT_STATUSES` and sums contract forecast into `forecast_total`.
  - `_get_active_rows(...)` currently filters vendor using `e.vendor`; must use `r.vendor`.
  - Existing lines and build-up modes include contract lines as economic contributions; these must be changed or clearly made informational only.
- `master_plan_it/master_plan_it/report/mpit_overview/`
  - Existing Script Report must be modified, not replaced by a custom Desk Page.
- `master_plan_it/fixtures/notification.json`
  - Conditions reference legacy contract statuses.
- Number cards/reports/charts contain legacy names such as `Expired Contracts`; audit and update.
- `locale/main.pot` and `locale/it.po` already exist. Continue using the `.po` workflow.

---

## 3. Implementation phases

Do not do this as one unstructured patch. Implement in phases, with tests after each phase if possible.

### Phase 1 — Schema and UX metadata

#### 3.1 `MPIT Project`

Update DocType JSON:

- Replace `workflow_state` options with recommended canonical values:

```text
Idea
Proposed
Approved
Deferred
Rejected
```

- Add field after `workflow_state`:

```text
fieldname: deferred_to_year
label: Defer To Year
fieldtype: Link
options: MPIT Year
mandatory_depends_on: eval:doc.workflow_state == 'Deferred'
depends_on: eval:doc.workflow_state == 'Deferred'
description: Select the year when this deferred project should return to the planning pipeline.
```

- Consider renaming the visible label of `workflow_state` from `Status` to `Project Stage` if it improves clarity. Do not rename the fieldname.
- Keep financial summary after the narrative/context fields. Suggested order:
  1. Overview: `title`, `workflow_state`, `deferred_to_year`, `cost_center`
  2. Planning window: `start_date`, `end_date`
  3. Description/Notes
  4. Financial Summary

Controller changes:

- Replace `VALID_PROJECT_STATUSES`.
- Add validation:
  - `deferred_to_year` required when status is `Deferred`.
  - `deferred_to_year` must be empty or ignored when status is not `Deferred`? Preferred UX: do not clear it automatically; it can remain as history after promotion.
  - If status is `Deferred`, referenced `MPIT Year` must exist.

#### 3.2 `MPIT Contract`

Update DocType JSON:

- `status` options:

```text
Active
Concluded
```

- `status` label: `Calculated Status`.
- `status` should be `read_only = 1`.
- Remove manual/status semantics from help text.
- Keep `auto_renew` but improve label/help:
  - Label: `Auto Renew Terms`
  - Description: see help text table below.
- Make `terms` mandatory at controller level; table `reqd` may remain as UX hint but server validation is authoritative.
- Keep `vendor` on contract because generated expense rows need it.
- Review whether `next_renewal_date` is still meaningful. If it is only for notification/report UX, keep it computed/read-only; if it only served legacy manual renewal, mark it read-only or remove from visible form after confirming tests.

Controller changes:

- Remove legacy status normalization.
- Add `calculate_contract_status(today=None)` helper.
- Set `self.status` during validation or before save.
- Validate `terms` non-empty before attempting current term/annual summary logic.
- Compute term end dates and no-overlap before calculating status.
- Implement auto-renew in a deterministic helper called during validation/before save, before annual summaries.

Recommended structure:

```python
class MPITContract(Document):
    def validate(self):
        self._validate_head()
        self._validate_terms_present()
        self._sync_term_amounts()
        self._auto_compute_term_end_dates()
        self._validate_terms_no_overlap()
        self._auto_renew_terms_to_existing_year_horizon()
        self._auto_compute_term_end_dates()
        self._validate_terms_no_overlap()
        self.status = self._calculate_status()
        self._compute_current_term()
        self._compute_annual_summaries()

    def on_update(self):
        sync_contract_expenses(self.name)
```

Comment non-obvious parts in English:

```python
# Contract rows are generators, not budget rows. The actual budget entry is the generated MPIT Expense row.
# Sync must be idempotent because on_update runs after every save.
```

#### 3.3 `MPIT Expense`

DocType JSON / UX:

- Parent `vendor` field:
  - Preferred: hide it from normal form UX, keep only temporarily for migration/backward compatibility.
  - Label if kept visible in admin: `Deprecated Parent Vendor`.
  - Description: see table below.
- Child row `vendor`:
  - Keep visible in grid.
  - Move near `row_description` and `row_phase`, before amount fields.
  - Description: see table below.
- `replaces_row_name`:
  - Keep near row status/phase or immediately after `row_phase`/`row_state` so the lifecycle relationship is visible.
  - Description: see table below.
- `external_reference`:
  - Make read-only and hidden/collapsed if possible. It is technical metadata.

Server validation:

- For ordinary expense rows, require `row.vendor`.
- For rows generated from contract, vendor must be set automatically.
- Do not require vendor for plafond rows unless explicitly approved later.

### Phase 2 — Data migration patches

Create a new patch module, for example:

```text
master_plan_it/patches/v1_1/refactor_budget_model_project_contract_expense.py
```

Add it to `master_plan_it/patches.txt`. If using `[post_model_sync]`, ensure existing patch ordering remains valid. If not using post-model-sync, call `frappe.reload_doc(...)` for changed DocTypes before accessing new fields.

Patch responsibilities:

1. Reload changed DocTypes as needed:
   - `mpit_project`
   - `mpit_contract`
   - `mpit_expense`
   - `mpit_expense_row`
2. Validate `MPIT Year` for current year + 1 exists before migrating `Cancelled` projects.
3. Migrate project statuses:
   - `Open -> Idea`
   - `On Hold -> Idea`
   - `Completed -> Approved`
   - `Cancelled -> Deferred`, plus `deferred_to_year = current year + 1`
4. Migrate contract statuses by recalculating, not by mapping legacy values directly.
5. Migrate `MPIT Expense Row.vendor`:
   - For each row where `row.vendor` is empty and parent `MPIT Expense.vendor` is set, copy parent vendor to row vendor.
   - Do not overwrite existing row vendor.
6. Do not delete parent `MPIT Expense.vendor` values in the same patch.
7. Do not create years automatically.
8. Fail loudly on inconsistent data that cannot be safely migrated.

Patch transaction principle:

```python
# If this patch fails, Frappe will roll back the transaction.
# Do not catch and suppress exceptions that would leave economic data half-migrated.
```

### Phase 3 — Project scheduler

Create a small scheduled task module, for example:

```text
master_plan_it/master_plan_it/tasks.py
```

Add to `hooks.py`:

```python
scheduler_events = {
    "daily": [
        "master_plan_it.master_plan_it.tasks.promote_deferred_projects",
    ],
}
```

Task behavior:

```python
def promote_deferred_projects():
    """Promote deferred projects when their target year becomes current."""
```

Rules:

- Resolve current `MPIT Year` by today between `start_date` and `end_date`.
- If no current `MPIT Year` exists, do nothing and log/debug clearly.
- Find projects with `workflow_state = 'Deferred'` and `deferred_to_year = current_year_name`.
- Set `workflow_state = 'Proposed'`.
- Keep `deferred_to_year` unchanged as historical trace.
- Do not touch any other status.
- The task must be safe to run repeatedly.

### Phase 4 — Contract service refactor

Replace manual actualization with automatic sync.

Recommended service naming:

```text
master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.py
sync_contract_expenses(contract_name: str) -> dict
```

or move the service to a neutral module if the controller becomes too large:

```text
master_plan_it/master_plan_it/services/contract_expense_sync.py
```

Preferred if the file grows too much: create a service module and keep the controller thin.

Remove from `mpit_contract.js`:

- `Create Actual for Current Year` button.
- Manual call to `create_actual_from_contract`.
- Actualization intro that invites manual action.

You may keep a read-only status intro such as:

```text
Contract expenses are synchronized automatically after saving.
```

No manual button.

#### 4.1 Sync scope

For each saved contract:

- Read all existing `MPIT Year` records.
- For each year overlapped by at least one contract term, generate/update one `MPIT Expense` document for that contract-year.
- Do not create expenses for missing years.
- Do not generate from contracts without terms; such contracts should not validate.

Generated `MPIT Expense` header:

```python
{
    "expense_kind": "Ordinary",
    "expense_title": _("Actual from contract {0} - {1}").format(contract.name, year_name),
    "workflow_state": "Closed",
    "year": year_name,
    "cost_center": contract.cost_center,
    "contract": contract.name,
    "uses_plafond": 0,
    "is_extra": 0,
}
```

Do not set `MPIT Expense.vendor` as official data. If the field still exists for migration/backward compatibility, either leave it blank or set it only as deprecated compatibility data. Reports must not use it.

Generated `MPIT Expense Row`:

```python
{
    "row_state": "Active",
    "row_phase": "Actual",
    "row_description": _("{0} [{1}]").format(contract.description or contract.name, term.name),
    "vendor": contract.vendor,
    "external_reference": stable_reference,
    "amount": annual_contribution_net,
    "amount_includes_vat": 0,
    "start_date": period_start,
    "end_date": period_end,
    "distribution": "all",
}
```

#### 4.2 Stable external reference

Use a deterministic format. Existing style is acceptable if extended carefully:

```text
MPIT_CONTRACT_ACTUAL::{year_name}::{contract_name}::TERM::{term_row_name}
```

If term row names are unstable across migration/import, consider a stronger reference based on term date range, but do not change this without checking existing generated rows.

#### 4.3 Update behavior

For rows owned by the sync service, identified by `external_reference` prefix:

- If expected reference exists, update derived fields if changed:
  - row description
  - vendor
  - amount
  - VAT fields if applicable
  - start/end period
  - distribution
- If expected reference does not exist, append a new row.
- If an existing generated row no longer has an expected reference because term dates changed, do not delete it. Preferred: mark `row_state = Cancelled` and add an explanatory note.
- Do not touch manual rows without the sync prefix.
- If multiple non-cancelled generated expenses exist for the same contract-year, fail with a clear error instead of guessing which one is authoritative.

#### 4.4 Prevent recursion and side effects

`on_update` will save or insert `MPIT Expense`. That should not re-save the contract. Avoid recursive contract saves.

If you need flags, use explicit document flags and keep them local. Do not rely on global mutable state.

### Phase 5 — Expense replacement logic

Update `MPITExpense._validate_row_replacements()`.

Required logic:

1. Normalize `replaces_row_name`.
2. Resolve target rows from the same parent document.
3. Forbid self replacement.
4. Forbid targets outside this expense.
5. Forbid replacing `Actual` rows.
6. Detect cycles.
7. After validation, set each target row’s `row_state = 'Replaced'`.
8. If a row no longer replaces a target after user edit, do not automatically restore the old row to `Active` unless this is explicitly implemented with a safe previous-state comparison. Default safer behavior: once a row has been replaced, it stays replaced unless the user manually changes it.

Comment example:

```python
# Replacement preserves auditability: the old commercial assumption remains visible,
# but it stops contributing to totals once a newer row supersedes it.
```

Update replacement query:

- Exclude `Actual` rows from `get_expense_row_replacement_options`.
- Prefer showing only same-parent rows.
- Include row phase, state, vendor, amount, and description in the label if feasible.

### Phase 6 — Financial engine refactor

This is the highest regression-risk area.

#### 6.1 Remove contract independent contribution from official totals

In `financial_engine.py`, official budget totals must no longer add `contract_forecast_total` into `forecast_total`.

Before concept:

```python
forecast_total = contract_forecast_total + expense_forecast_total
```

After concept:

```python
forecast_total = expense_forecast_total
```

Rationale comment:

```python
# Contracts generate expense rows. Adding contract forecasts here would double-count
# the same economic obligation once automatic contract actualization is enabled.
```

You may keep a separate informational metric for contract sync/coverage if useful, but name it clearly so it is not mistaken as part of budget totals. Example:

```text
contract_context_total
```

or remove the column from the standard summary if it confuses users.

#### 6.2 Vendor filtering

Replace vendor filtering based on parent expense:

```sql
AND e.vendor = %(vendor)s
```

with row-level filtering:

```sql
AND r.vendor = %(vendor)s
```

Return `vendor` in datasets as `r.vendor`, not `e.vendor`.

#### 6.3 Project inclusion rules

Project state must govern whether linked expenses enter actual/prevision/report buckets.

Define effective project for a row:

- Direct: `MPIT Expense.project`
- Contract-linked: if expense has `contract`, use `MPIT Contract.project` as effective project when the expense has no direct project.

Do not allow double counting.

Budget inclusion rules:

| Effective project state | Actual budget | Forecast/prevision | Report bucket | Notes |
|---|---:|---:|---|---|
| No project | Yes | Yes | Ordinary | Normal cost center expense |
| `Idea` | No | Optional/display only | Ideas | Do not pollute official budget |
| `Proposed` | No | Yes | Proposals | Planning decision input |
| `Approved` | Yes | Yes | Approved Budget | Operational budget |
| `Deferred` | No in current year | No in current year | Deferred | Scheduler moves to Proposed when its year arrives |
| `Rejected` | No | No | Rejected/History | Excluded |

Implementation rule:

- Separate “official totals” from “planning buckets”.
- Do not include `Idea`, `Deferred`, or `Rejected` in official totals.
- Include `Proposed` in forecast/prevision buckets but not actual budget.
- Include `Approved` in both forecast and actual budget.
- Rows with no project are ordinary and included.

If current tests assume project filter does not affect contracts, update tests and documentation because contract-generated expenses are now expense rows and project context can be resolved through the linked contract.

#### 6.4 Maturity and replacement

Do not implement a broad heuristic that discards all estimates when any actual exists for the expense. That can lose unrelated line items.

The authoritative rule is:

- Totals include only active rows.
- Replacement converts superseded rows to `Replaced`.
- Users express lifecycle replacement through `replaces_row_name`.

If you add a computed “effective amount” field for display, make it explicit and test separately.

### Phase 7 — MPIT Overview UX/report redesign

Modify the existing Script Report under:

```text
master_plan_it/master_plan_it/report/mpit_overview/
```

Do not create a new Desk Page.

#### 7.1 Filter UX

Current `view_mode` mixes report layout with business meaning. Improve clarity:

Recommended filters, in order:

1. `year` — required.
2. `financial_view` — label: `Visualization`; options:
   - `Actual`
   - `Forecast`
   - `Actual with Estimates and Quotes`
3. `view_mode` — label: `Layout`; options:
   - `Summary`
   - `Build-up`
   - `Lines`
4. `cost_center`
5. `section_scope`
6. `expense_phase`
7. `vendor`
8. `contract`
9. `project`
10. `show_zero_rows`
11. Print filters last.

Use concise descriptions/help text. Do not create visual clutter.

#### 7.2 Report columns

Summary mode should be understandable for a non-developer user.

Suggested standard summary columns:

- `Cost Center`
- `Currently Spent` (`Actual` included in official budget)
- `Forecast` (according to selected visualization)
- `Approved Budget`
- `Proposals`
- `Ideas`
- `Extra`
- `Plafond`
- `Plafond Consumed`
- `Remaining`
- `Over`

If keeping legacy columns temporarily, do not keep misleading labels like `Forecast Contracts` as an official budget contributor.

Lines mode should show enough traceability:

- Source document link
- Source row hidden or available
- Description
- Cost Center
- Vendor
- Contract
- Project / Effective Project
- Project Bucket
- Phase
- Funding
- Period / Spend Date
- Amount Net
- State

Use native `Link` columns where possible.

#### 7.3 Report summary cards

Recommended cards:

- `Currently Spent`
- `Forecast`
- `Approved Budget`
- `Proposals`
- `Plafond Remaining`
- `Extra`
- `Over Plafond`

Indicators:

- Neutral/blue for totals.
- Green for remaining plafond.
- Orange for proposals/extra.
- Red for over plafond.

#### 7.4 Formatting

Existing JS formatter is acceptable only if it uses native report APIs and minimal semantic classes. Do not add CSS hacks.

Keep `get_datatable_options` if useful.

If you update labels, update `.po` strings.

### Phase 8 — Notifications, number cards, dashboards, print formats

Audit and update every reference to legacy project/contract statuses.

Targets to inspect at minimum:

- `master_plan_it/fixtures/notification.json`
- `master_plan_it/master_plan_it/number_card/**`
- `master_plan_it/master_plan_it/dashboard_chart_source/**`
- `master_plan_it/master_plan_it/report/mpit_renewals_window/**`
- `master_plan_it/master_plan_it/print_format/mpit_project_professional/mpit_project_professional.html`
- `docs/reference/03-workflows.md`
- `docs/reference/04-reports-dashboards.md`
- `docs/ux/field-help-text.md`
- Cypress tests under `cypress/e2e/`

Required changes:

- Replace legacy project statuses in print/report UI.
- Replace `Expired Contracts` with `Concluded Contracts` or another approved label.
- Renewal windows should no longer depend on legacy statuses. Use calculated `status = Active` and term/end date logic.
- Notifications must reference only valid canonical status values.

### Phase 9 — Translations and help text

Follow the current app approach using `locale/main.pot` and `locale/it.po`.

Translation rules:

- Keep source strings in English when using the recommended canonical/source-language policy.
- Use Italian translations in `it.po`.
- Do not concatenate translated strings.
- Use placeholders `{0}` when variables are needed.
- Update `docs/reference/12-i18n.md` if the workflow changes.

#### 9.1 Help text table

Use these descriptions in DocType JSON where applicable. Add/update translations in `it.po`.

| DocType | Field | English label | English description | Italian label | Italian description |
|---|---|---|---|---|---|
| MPIT Project | `workflow_state` | Project Stage | Controls whether linked expenses are included in the operating budget, shown only as planning, deferred, or excluded. | Stato progetto | Determina se le spese collegate entrano nel budget operativo, restano solo in pianificazione, vengono rimandate o escluse. |
| MPIT Project | `deferred_to_year` | Defer To Year | Required for deferred projects. When this year becomes current, the scheduler moves the project back to Proposed. | Rimanda all’anno | Obbligatorio per i progetti rimandati. Quando questo anno diventa corrente, il job schedulato riporta il progetto a Proposto. |
| MPIT Contract | `status` | Calculated Status | System-calculated status based on contract terms. Active means at least one term is still open or current; Concluded means all terms ended before today. | Stato calcolato | Stato calcolato dal sistema in base alle righe contratto. Attivo indica almeno una riga aperta o corrente; Concluso indica che tutte le righe sono terminate prima di oggi. |
| MPIT Contract | `auto_renew` | Auto Renew Terms | When enabled, saving the contract creates missing yearly terms within existing MPIT Years, without duplicates or overlapping periods. | Rinnovo automatico righe | Se attivo, al salvataggio del contratto vengono create le righe annuali mancanti entro gli anni MPIT esistenti, senza duplicati o periodi sovrapposti. |
| MPIT Contract | `terms` | Terms | Contract economic periods. At least one row is required. These rows generate closed Actual expenses for existing MPIT Years. | Righe contratto | Periodi economici del contratto. È richiesta almeno una riga. Da queste righe vengono generate spese effettive chiuse per gli anni MPIT esistenti. |
| MPIT Contract Term | `from_date` | From Date | Start date of this contract economic period. | Data inizio | Data di inizio del periodo economico del contratto. |
| MPIT Contract Term | `to_date` | To Date | End date of this contract economic period. Leave empty only for an open-ended active term. | Data fine | Data di fine del periodo economico del contratto. Lascia vuoto solo per una riga attiva senza scadenza. |
| MPIT Contract Term | `amount` | Amount | Amount for the selected billing cycle. The yearly contribution is calculated from dates and billing cycle. | Importo | Importo riferito al ciclo di fatturazione selezionato. Il contributo annuale viene calcolato da date e ciclo. |
| MPIT Contract Term | `billing_cycle` | Billing Cycle | Defines how the amount matures over the year. Existing cycles are preserved to avoid changing historical calculations. | Ciclo di fatturazione | Definisce come l’importo matura nell’anno. I cicli esistenti vengono preservati per non alterare i calcoli storici. |
| MPIT Expense | `vendor` | Deprecated Parent Vendor | Deprecated compatibility field. The official vendor is set on each expense row. Do not use this field for reporting. | Fornitore testata deprecato | Campo mantenuto solo per compatibilità. Il fornitore ufficiale è quello indicato su ogni riga spesa. Non usare questo campo nei report. |
| MPIT Expense | `project` | Project | Optional planning context. Project stage controls whether linked expense rows are included in budget or planning buckets. | Progetto | Contesto opzionale di pianificazione. Lo stato del progetto determina se le righe spesa collegate entrano nel budget o nei bucket di pianificazione. |
| MPIT Expense | `contract` | Contract | Optional contract context. Contract-generated expenses are synchronized automatically and are counted only through expense rows. | Contratto | Contesto contratto opzionale. Le spese generate da contratto sono sincronizzate automaticamente e vengono conteggiate solo tramite le righe spesa. |
| MPIT Expense | `uses_plafond` | On Plafond | Enable only when this ordinary expense consumes a selected plafond. The plafond may belong to another cost center but must be in the same year. | Su plafond | Abilita solo quando questa spesa ordinaria consuma un plafond selezionato. Il plafond può appartenere a un altro centro di costo, ma deve essere dello stesso anno. |
| MPIT Expense | `plafond_expense` | Plafond Reference | Select the plafond consumed by this expense. Only non-cancelled plafonds from the same year are valid. | Plafond di riferimento | Seleziona il plafond consumato da questa spesa. Sono validi solo plafond non annullati dello stesso anno. |
| MPIT Expense | `is_extra` | Extra | Enable only for unplanned or out-of-scope actual expenses. Extra expenses do not consume plafond. | Extra | Abilita solo per spese effettive non pianificate o fuori perimetro. Le spese extra non consumano plafond. |
| MPIT Expense Row | `vendor` | Vendor | Official vendor for this expense row. Reports and vendor filters use this field, not the parent expense vendor. | Fornitore | Fornitore ufficiale della riga spesa. Report e filtri fornitore usano questo campo, non il fornitore della testata. |
| MPIT Expense Row | `row_phase` | Phase | Estimate, Quote, or Actual. Actual rows are definitive and cannot be replaced by another row. | Fase | Stima, Preventivo o Effettivo. Le righe Effettivo sono definitive e non possono essere sostituite da altre righe. |
| MPIT Expense Row | `row_state` | Row State | Active rows contribute to totals. Replaced and Cancelled rows remain visible for audit but are excluded from totals. | Stato riga | Le righe Attive contribuiscono ai totali. Le righe Sostituite e Annullate restano visibili per verifica ma sono escluse dai totali. |
| MPIT Expense Row | `replaces_row_name` | Replaces Row | Select an Estimate or Quote row from the same expense to supersede. The selected row will be marked as Replaced automatically. | Sostituisce riga | Seleziona una riga Stima o Preventivo della stessa spesa da superare. La riga selezionata verrà marcata automaticamente come Sostituita. |
| MPIT Expense Row | `external_reference` | External Reference | Technical reference used by automatic synchronization. Do not edit manually. | Riferimento esterno | Riferimento tecnico usato dalla sincronizzazione automatica. Non modificare manualmente. |
| MPIT Overview | `financial_view` | Visualization | Choose whether the report shows actual budget, forecast, or actual values enriched with estimates and quotes. | Visualizzazione | Scegli se il report mostra il budget effettivo, la previsione oppure gli effettivi integrati con stime e preventivi. |
| MPIT Overview | `view_mode` | Layout | Select how the report is displayed: summary, build-up, or detailed lines. | Layout | Seleziona come visualizzare il report: riepilogo, dettaglio per blocchi o righe analitiche. |
| MPIT Overview | `section_scope` | Section Scope | Limits the report to a specific economic area without changing the underlying budget rules. | Ambito sezione | Limita il report a una specifica area economica senza modificare le regole di budget sottostanti. |
| MPIT Overview | `show_zero_rows` | Show Zero Rows | Show rows with zero value for audit and troubleshooting. Disable for normal reading. | Mostra righe a zero | Mostra le righe con valore zero per verifica e diagnosi. Disabilita per la lettura ordinaria. |

#### 9.2 Required status translations

Add/update these translations in `it.po`:

```po
msgid "Proposed"
msgstr "Proposto"

msgid "Approved"
msgstr "Approvato"

msgid "Deferred"
msgstr "Rimandato"

msgid "Rejected"
msgstr "Rifiutato"

msgid "Concluded"
msgstr "Concluso"

msgid "Calculated Status"
msgstr "Stato calcolato"

msgid "Project Stage"
msgstr "Stato progetto"

msgid "Defer To Year"
msgstr "Rimanda all’anno"

msgid "Auto Renew Terms"
msgstr "Rinnovo automatico righe"

msgid "Actual with Estimates and Quotes"
msgstr "Effettivo con stime e preventivi"
```

Also remove or leave obsolete translations harmlessly, but ensure no current UI still displays obsolete labels such as `Pending Renewal`, `Renewed`, `Expired`, `On Hold`, `Completed`, or `Cancelled` for Project/Contract statuses.

### Phase 10 — Tests

Update existing tests; do not remove coverage to make the suite pass.

Minimum new/updated test coverage:

#### Project

- Legacy status migration mapping.
- `Deferred` requires `deferred_to_year`.
- Scheduler promotes only `Deferred` projects whose target year is current.
- Scheduler is idempotent.
- Scheduler does not touch `Rejected`, `Approved`, `Idea`, `Proposed`.

#### Contract

- Contract without terms fails validation.
- Contract status becomes `Active` when any term is open/current.
- Contract status becomes `Concluded` when all terms ended before today.
- Legacy statuses no longer validate.
- Auto-renew creates missing non-overlapping terms only within existing MPIT Years.
- Repeated saves do not create duplicate terms.
- Open-ended latest term does not generate a successor.
- Automatic expense sync creates `Closed` ordinary expenses.
- Sync uses only existing MPIT Years.
- Sync is idempotent.
- Changed term amount/date updates generated rows instead of duplicating.
- Deleted/no-longer-overlapping generated references are cancelled or handled deterministically.

#### Expense

- Row vendor migration copies parent vendor only when row vendor is empty.
- Ordinary rows require row vendor.
- Vendor filter uses row vendor.
- Replacing an `Estimate` marks target `Replaced`.
- Replacing a `Quote` marks target `Replaced`.
- Replacing an `Actual` is rejected.
- Self replacement is rejected.
- Cyclic replacement is rejected.
- Replaced rows do not contribute to totals.
- Cross-cost-center plafond consumption still works.
- Multiple non-cancelled plafonds for same year/cost center remain forbidden.

#### Financial engine / reports

- Official `forecast_total` no longer includes standalone contract forecast.
- Contract-generated actual rows are counted through `MPIT Expense` only.
- No double count when a contract has generated expenses.
- Project state inclusion matches the approved table.
- Effective project via linked contract is handled consistently if implemented.
- `MPIT Overview` filters by row vendor.
- `MPIT Overview` contract filter returns generated expense rows, not independent contract totals.
- `MPIT Overview` project filter respects project buckets.
- Existing print/export paths do not break.

#### Translations

- Run/update translation sync tests.
- Confirm new help text strings are extracted/translated.
- Confirm obsolete button `Create Actual for Current Year` is no longer present in client JS or translation expectations.

### Phase 11 — Verification commands

Adapt paths/site names to the actual environment. Do not run destructive commands without approval.

Recommended commands after implementation:

```bash
cd /home/frappe/frappe-bench
bench --site localhost migrate
bench --site localhost clear-cache
bench --site localhost build --app master_plan_it
bench --site localhost run-tests --app master_plan_it
```

If the project uses pytest directly outside bench, run the existing project test command from `pyproject.toml` only after checking local docs.

Static checks:

```bash
grep -RIn "Pending Renewal\|Renewed\|Expired\|On Hold\|Completed\|Create Actual for Current Year" apps/master_plan_it/master_plan_it apps/master_plan_it/docs apps/master_plan_it/cypress || true
grep -RIn "forecast_contracts\|ACTIVE_CONTRACT_STATUSES\|VALID_CONTRACT_STATUSES\|e.vendor" apps/master_plan_it/master_plan_it apps/master_plan_it/tests || true
```

Expected outcome:

- Legacy terms may remain only in old translations, historical documentation, or explicit migration notes.
- They must not remain in active business logic, report filters, DocType option values, notifications, or tests unless deliberately documented.

Manual UX smoke checks:

1. Create `MPIT Year` current and current+1.
2. Create a contract with one term and `auto_renew=1`.
3. Save contract.
4. Verify status is calculated and read-only.
5. Verify generated expense exists, is `Closed`, and has `Actual` rows with row vendor.
6. Save contract again.
7. Verify no duplicate terms and no duplicate expense rows.
8. Create an estimate row, then a quote replacing it.
9. Save expense and verify the estimate becomes `Replaced`.
10. Try replacing an actual row and verify validation blocks it.
11. Create a cross-cost-center plafond consumption and verify it still validates.
12. Open `MPIT Overview` and verify totals derive from expenses only.
13. Apply vendor filter and verify it filters by row vendor.
14. Apply project filter and verify project state bucket/inclusion behavior.

---

## 4. Definition of Done

The task is complete only when all conditions below are true.

- No official budget total is calculated by summing standalone contract contribution plus generated expense rows.
- Contract expenses are generated automatically and idempotently after contract save.
- Generated contract expenses are `Closed` and have `Actual` rows.
- Contract sync only uses existing `MPIT Year` records.
- Contract status is fully calculated as `Active` / `Concluded` and is not manually controlled.
- Contract cannot be saved without at least one term.
- Project statuses are migrated and validated under the new model.
- `Deferred` projects promote to `Proposed` through a daily idempotent scheduler job.
- Row replacement automatically marks targets as `Replaced`.
- No row can replace an `Actual` row.
- Vendor reporting/filtering uses `MPIT Expense Row.vendor`.
- Parent `MPIT Expense.vendor` is not used for economic logic.
- Existing single-plafond-per-year-cost-center validation remains active.
- Cross-cost-center plafond consumption remains allowed.
- `MPIT Overview` is updated in place using native Script Report behavior.
- Report labels and flow are understandable for a non-developer user.
- Help texts are present and translated.
- `locale/main.pot` / `locale/it.po` are updated consistently with the project’s current i18n workflow.
- Tests cover the new economic rules and previous regression-prone behavior.
- No custom Desk Page, CSS hack, or frontend-only economic computation has been introduced.
- Any remaining legacy field kept for compatibility is explicitly marked as deprecated and not used by current logic.

---

## 5. Final report format expected from the agent

When finished, report only:

1. Files changed.
2. Data patches added and why.
3. Migration/test commands executed with result.
4. Any behavior intentionally kept for backward compatibility.
5. Any unresolved issue or product decision still needed.
6. Exact evidence that no double counting remains in `MPIT Overview`.

Do not provide generic summaries. Do not claim success without test/migration evidence.
