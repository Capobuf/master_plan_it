# Open Issues & Technical Debt

Last updated: 2026-01-04T00:30:00+01:00

---

## Summary

| Status | Count |
|--------|-------|
| ✅ Resolved | 13 |
| 🟡 Open | 48 |

---

## 🟡 Open Issues

### O-001: docs/ux/ and docs/questions-*.md have v2 terminology
- **Location**: `docs/ux/mpit_budget_engine_v2_ui.md`, `docs/questions-mpit_budget_engine_v3_decisions.md`
- **Issue**: Legacy planning docs still use Baseline/Forecast terminology
- **Decision**: Not priority — these are internal decision docs, not reference
- **Note**: Includes findings from O-013 (Conflicting decisions doc)
- **Root Cause**: **Documentation Rot**. Docs were written for v2/Planning phase and not updated for v3 implementation.

### O-002: 10-money-vat-annualization.md Section 5 "Dual-Mode Controller"
- **Location**: `docs/reference/10-money-vat-annualization.md` L73-177
- **Issue**: Complex code block — needs verification against actual code
- **Status**: Update doc to reflect refactor (logic moved to `amounts.py`)
- **Root Cause**: **Refactor Drift**. The "Dual-Mode" logic exists but was refactored into `amounts.py` (helpers) and `mpit_project.py` (inline), while the doc describes a centralized controller version. Logic is valid, location changed.


### O-004: 07-projects-multi-year.md workflow verification
- **Location**: `docs/how-to/07-projects-multi-year.md`
- **Issue**: Workflow described may or may not match current code
- **Status**: **HIGH PRIORITY**. Downgrade project validation to rely on Planned Items.
- **Root Cause**: **Dead Code Enforcement**. The guide mandates "Allocations" because `mpit_project.py` still *enforces* them for approval (L124), despite the Budget Engine v3 ignoring them in favor of "Planned Items". This traps users in a legacy workflow.

### O-005: README per DocType (backlog)
- **Decision**: Create README.md in doctype folders for agents/devs
- **Decision**: Create README.md in doctype folders for agents/devs
- **Status**: Not priority, add to backlog
- **Root Cause**: **Feature Request**. Low priority enhancement for developer experience.



### O-007: 10-printing-and-report-print-formats.md references v2 report
- **Location**: `docs/reference/10-printing-and-report-print-formats.md` L105-109
- **Issue**: Example folder structure references `mpit_current_budget_vs_actual/` which doesn't exist
- **Status**: Update required

### O-008: CHANGELOG.md v0.1.0 references "MPIT User Preferences" but code uses "MPIT Settings"
- **Location**: `CHANGELOG.md` L15-24, L33-38, L51
- **Issue**: Phase 1 documents `MPIT User Preferences` DocType and `mpit_user_prefs.py` helper. Code now uses `MPIT Settings` as singleton.
- **Evidence**: `mpit_settings.json` exists; no `mpit_user_preferences.json` in doctype folder
- **Status**: Historical changelog — document as note or leave as-is
- **Root Cause**: **Refactor Name Drift**. The feature "User Preferences" (Per-User) was refactored to "MPIT Settings" (Tenant-Wide) but the Changelog/Docs kept the old name. Logic in `mpit_defaults.py` correctly maps to Settings.

### O-009: docs/ux/mpit_budget_engine_v2_ui.md is entirely obsolete v2 design doc
- **Location**: `docs/ux/mpit_budget_engine_v2_ui.md` (56 lines)
- **Issue**: Full UX spec for v2 engine with Baseline/Forecast, spread/rate schedule logic, active forecast toggle — all removed in v3
- **Evidence**: Code has `budget_type: Live/Snapshot`, no `is_active_forecast`, no `spread_months` in contract fields
- **Status**: Should be deleted or moved to archive. Currently misleads agents.
- **Root Cause**: **Documentation Rot**. Preserved reference to obsolete v2 design that was never cleaned up.

### O-010: 01-architecture.md uses "baseline" terminology
- **Location**: `docs/explanation/01-architecture.md` L12-17
- **Issue**: "Budgets approved at the start of the year become immutable" and "Contracts/subscriptions are curated records that drive renewals" — accurate but uses "baseline" wording
- **Status**: Low priority, wording is contextually correct. Includes O-039 findings.

### O-011: field-help-text.md inventory mismatch
- **Location**: `docs/ux/field-help-text.md` L24-34
- **Issue**: Lists DocTypes but counts don't match v3 DocType set. Missing: `MPIT Budget Addendum`, `MPIT Planned Item`
- **Evidence**: `doctype/` folder contains `mpit_budget_addendum/`, `mpit_planned_item/` which are not in inventory
- **Status**: Update required for completeness
- **Root Cause**: **Documentation Rot**. DocTypes `Addendum` and `Planned Item` were added late in v3 cycle and missed the manual inventory update.

### O-012: DOCS_MAP.md missing v3 report references
- **Location**: `DOCS_MAP.md` L16-23
- **Issue**: Reference section lists `04-reports-dashboards.md` generically but doesn't point to updated v3 reports or chart sources doc
- **Status**: Low priority enhancement
- **Root Cause**: **Documentation Rot**. Map file was not maintained as new reports were added.



### O-014: 08-data-sources-for-charts.md is outdated
- **Location**: `docs/reference/08-data-sources-for-charts.md` L1-3
- **Issue**: States "Generated from code on 2026-01-02" but DocType inventory may drift from code changes
- **Status**: Monitor — regenerate periodically
- **Root Cause**: **Generated Content Drift**. File is likely the output of a script/LLM run that is now stale.

### O-015: ADR 0007 references "Custom" recurrence not in code
- **Location**: `docs/adr/0007-money-naming-printing.md` L45-49
- **Issue**: Documents `Recurrence: Monthly, Quarterly, Annual, Custom, None` but `mpit_budget_line.json` shows only `Monthly, Quarterly, Annual, None` — no `Custom` option
- **Evidence**: 
  - `mpit_budget_line.json` L190 options are `Monthly\nQuarterly\nAnnual\nNone`
  - `annualization.py` L8 mentions "Custom" in docstring but L157 `validate_recurrence_rule()` only allows `{Monthly, Quarterly, Annual, None}`
  - No controller handles "Custom" recurrence case
- **Status**: ADR needs update (remove Custom) or code needs Custom implementation
- **Root Cause**: **Abandoned Feature / Spec Drift**. "Custom" recurrence was specified in ADR 0007 but never implemented in `annualization.py` (only Monthly/Quarterly/Annual/None).

### O-016: CHANGELOG.md references spec folders that don't exist
- **Location**: `CHANGELOG.md` L141-168, L8
- **Issue**: File structure shows `spec/doctypes/mpit_user_preferences.json` and `patches/v0_1_0/backfill_vat_fields.py` — paths that may not exist in current repo structure
- **Status**: Verify paths exist; update if stale
- **Root Cause**: **Dev-Only Artifacts**. The `spec/` folder referenced in Changelog was likely a local development artifact or uncommitted folder that did not make it to the final repo structure.

---

## Technical Debt Issues (General Code Quality)

### O-023: test_smoke.py missing v3 DocTypes
- **Location**: `master_plan_it/tests/test_smoke.py` L15-24
- **Issue**: `test_required_doctypes_exist()` lists only v2 DocTypes. Missing: `MPIT Budget Addendum`, `MPIT Planned Item`, `MPIT Settings`, `MPIT Year`
- **Evidence**: Code at L15-24 doesn't include new v3 DocTypes despite them being critical
- **Impact**: MEDIUM — Smoke test gives false confidence that install is complete
- **Status**: Add missing DocTypes to required list
- **Root Cause**: **Update Gap**. Smoke test list was not updated when new v3 DocTypes (Addendum, Planned Items) were added.

### O-024: Empty stub controllers for child tables
- **Location**: 
  - `mpit_project_quote/mpit_project_quote.py` — only `pass`
  - `mpit_project_allocation/mpit_project_allocation.py` — only `pass`
- **Issue**: Child table controllers are empty stubs without any validation or computed fields
- **Evidence**: Both files contain only `class ... (Document): pass`
- **Impact**: LOW — Child table validation is handled by parent controller (mpit_project.py)
- **Status**: Acceptable pattern for Frappe child tables; add clarifying docstrings
- **Root Cause**: **Frappe Pattern**. Child table controllers are often empty in Frappe as validation is handled by the parent doc. not strictly an issue but an observation.

### O-025: Empty test stubs in DocType folders
- **Location**: 
  - `mpit_settings/test_mpit_settings.py` — class with only `pass`
  - `mpit_year/test_mpit_year.py` — class with only `pass`
- **Issue**: Empty test classes provide no coverage
- **Evidence**: Both files contain `class Test...: pass`
- **Impact**: LOW — These DocTypes are simple data containers but having empty tests may mask future regressions
- **Status**: Either delete empty test files or add minimal validation tests
- **Root Cause**: **Boilerplate**. Files were auto-created by framework scaffolding ("bench new-doctype") and never populated.

### O-026: Bare 'except Exception' handlers without context logging
- **Location**: Multiple files (12 occurrences)
  - `mpit_budget.py` L158, L167, L444, L824, L837
  - `api/dashboard.py` L281
  - `budget_refresh_hooks.py` L177
  - `test_no_forbidden_metadata_paths.py` L32
- **Issue**: Some bare `except Exception:` blocks swallow errors without logging context
- **Evidence**: L158, L444, L167 don't log the error traceback
- **Impact**: MEDIUM — Silent failures make debugging harder
- **Status**: Review each handler; most already log errors (L824, L837), some need improvement
- **Root Cause**: **Anti-Pattern**. Catch-all exceptions were likely added for resilience during rapid dev but lack observability.



### O-028: pytest not used — test_budget_engine_v2.py imports missing
- **Location**: `master_plan_it/tests/test_budget_engine_v2.py` L108, L113, L123
- **Issue**: Test file uses `pytest.raises()` but doesn't import pytest. Line 1-5 don't show pytest import.
- **Evidence**: `with pytest.raises(frappe.ValidationError):` at L108 but no `import pytest`
- **Impact**: HIGH — Tests will fail with NameError: pytest is not defined
- **Status**: Add `import pytest` or convert to FrappeTestCase assertions
- **Root Cause**: **Code Defect**. Basic linting/import error missed in code review.

### O-029: devtools/test_print.py uses wrong field names — will fail at runtime
- **Location**: `master_plan_it/devtools/test_print.py` L57, L67, L74
- **Issue**: Multiple incorrect field names that don't exist in current DocType schemas:
  - L57: `year_name` should be `year` (MPIT Year field name changed)
  - L67: Missing `budget_type` field (required in v3)
  - L74: `includes_vat` should be `amount_includes_vat`
- **Evidence**: `mpit_year.json` has `year` not `year_name`; `mpit_budget.json` has `budget_type`; `mpit_budget_line.json` has `amount_includes_vat`
- **Impact**: HIGH — Script will crash with FieldNotFoundError on first run
- **Status**: Update field names to match current schema
- **Root Cause**: **Technical Debt / Stale Code**. Script uses old field names (`year_name`, `includes_vat`) that were renamed in v3 schema.

### O-030: devtools/rename_documents.py hardcodes year 2025
- **Location**: `master_plan_it/devtools/rename_documents.py` L22, L45
- **Issue**: Script has hardcoded `year or "2025"` fallback and `series_name = "BUD-2025-"` — time-sensitive code
- **Evidence**: L22: `year = doc.year or "2025"`; L45: `series_name = "BUD-2025-"`
- **Impact**: MEDIUM — Script won't work correctly for future years
- **Status**: Replace hardcoded year with dynamic current year
- **Root Cause**: **Implementation Shortcut**. Script was written with a hardcoded year "2025" instead of using `datetime.now().year` or an argument.

### O-031: Empty dashboard directory 'master_plan_it_overview_v3'
- **Location**: `dashboard/master_plan_it_overview_v3/`
- **Issue**: Directory exists but is empty (no JSON metadata)
- **Evidence**: `ls` command returned empty result
- **Impact**: LOW — Dead directory cluttering the repo
- **Status**: Delete directory or restore missing JSON
- **Root Cause**: **Deployment Artifact**. Likely created during a manual dashboard creation but the JSON definition was never exported or committed.

### O-032: Missing Workflow definition for MPIT Budget
- **Location**: Repository root (missing `fixtures/workflow.json` or `workflow/` definition)
- **Issue**: `MPIT Budget` uses `workflow_state` field but the corresponding `Workflow` document is not tracked in the repo
- **Evidence**: `mpit_budget.json` has `workflow_state` field; `fixtures/` contains only `role.json`; `workflow/` directory contains only `__init__.py`
- **Impact**: CRITICAL — New installs will lack the budget approval workflow (Draft -> Approved)
- **Status**: Export "MPIT Budget Workflow" (and states/transitions) to `fixtures/workflow.json` or as a new app module
- **Root Cause**: **Missing Configuration Export**. Workflow logic is used in code (`workflow_state` field) but the Workflow definition itself was not exported to fixtures.

### O-033: requirements.txt requests non-existent pytest version
- **Location**: `requirements.txt`
- **Issue**: Requests `pytest>=9.0.2` but current stable is ~8.3.x (as of early 2025)
- **Evidence**: `pytest>=9.0.2` in file
- **Impact**: HIGH — Installation will fail (No matching distribution found)
- **Status**: Downgrade to `pytest>=8.0.0` or similar
- **Root Cause**: **Typo / Hallucination**. Version `9.0.2` does not exist; developer likely guessed or typoed the version number.

### O-034: Duplicate documentation file with messy name
- **Location**: `docs/mpit_budget_engine_v3_decisions (3).md`
- **Issue**: File appears to be a browser download duplicate
- **Evidence**: Filename contains `(3)`
- **Impact**: LOW — Clutter/Confusion
- **Status**: Rename to `docs/mpit_budget_engine_v3_decisions.md` (if authoritative) or delete if duplicate of `questions-...`
- **Root Cause**: **File System Artifact**. Likely a result of a file download/copy operation (browser style numbering).



### O-036: Overlapping Print Format documentation
- **Location**: `docs/reference/08-printing-reports-pdf.md` and `10-printing-and-report-print-formats.md`
- **Issue**: Two files cover the same topic (Printing/PDF) with similar names
- **Evidence**: Both describe print formats; `08-` seems to be an earlier version or duplicate effort
- **Impact**: LOW — Fragmentation
- **Status**: Merge into `10-printing-and-report-print-formats.md` and delete `08-` (Resolves O-006)
- **Root Cause**: **Incomplete Migration**. Printing docs reference v2 report names that were renamed or removed in v3.

### O-037: Obsolete Project Allocation Guide
- **Location**: `docs/how-to/07-projects-multi-year.md`
- **Issue**: Guide mandates `MPIT Project Allocation` which is now legacy/stub in v3 (replaced by `MPIT Planned Item`)
- **Evidence**: "Add yearly allocations (mandatory for approval)"; Allocations controller is empty stub
- **Impact**: MEDIUM — Misleads users into using deprecated fields
- **Status**: Rewrite to use `MPIT Planned Item` flow
- **Root Cause**: **Documentation Rot**. Guide mandates a v2 workflow ("Project Allocations") that was replaced by v3 ("Planned Items").

### O-038: Empty Documentation Directories
- **Location**: `docs/tutorials/` and `docs/uiux/`
- **Issue**: Directories exist but are empty
- **Evidence**: `ls` command returned empty result
- **Impact**: LOW — Visual clutter
- **Status**: Delete directories
- **Root Cause**: **Scaffolding Artifacts**. Created during initial project setup (diátaxis structure) but never populated.



### O-040: Deprecated ADR 0004 (Allocations) active
- **Location**: `docs/adr/0004-project-allocations.md`
- **Issue**: ADR enforces Allocations, but v3 uses Planned Items. ADR should be marked Superseded.
- **Evidence**: Status is "Accepted", content describes deprecated model
- **Impact**: LOW — Historical confusion
- **Status**: Mark as Superseded by v3 logic (or new ADR)
- **Root Cause**: **Stale ADR**. Decision record was never updated to reflect the shift to Planned Items.

### O-041: Copilot Instructions reference broken tools
- **Location**: `.github/copilot-instructions.md`
- **Issue**: References `devtools/verify.py` (O-017/O-027 broken) and `architecture.md` (O-039 drift)
- **Evidence**: "Esegui master_plan_it.devtools.verify.run"
- **Impact**: LOW — Misleads AI agents
- **Status**: Update after fixing O-017 and O-039
- **Root Cause**: **Broken Reference**. Instructions point to broken scripts/docs (`verify.py`, `architecture.md`) identified in O-017/O-010.

### O-042: Hardcoded secrets in prod.env
- **Location**: `master-plan-it-deploy/prod.env`
- **Issue**: Contains `MYSQL_ROOT_PASSWORD=changeme` committed to repo
- **Evidence**: File `prod.env` in repo
- **Impact**: MEDIUM — Security risk if deployed without change
- **Status**: Remove file or replace content with placeholders and add to .gitignore
- **Root Cause**: **Security Issue**. Secrets file was accidentally committed to the repository.
 
 ### O-043: Infrastructure Divergence (Dev vs Prod)
 - **Location**: `master-plan-it-deploy/compose.yml` vs `compose.prod.yaml`
 - **Issue**: Significant configuration drift between environments:
   - **DB Version**: Dev uses `mariadb:10.8`, Prod uses `mariadb:10.6`. Rischio di usare feature 10.8 non supportate in prod.
   - **Process Management**: Dev uses `config/mpit-entrypoint.sh` + `Procfile`, Prod uses inline script in `compose.prod.yaml`.
 - **Evidence**: `image: mariadb:10.8` (Dev) vs `image: mariadb:10.6` (Prod).
 - **Impact**: MEDIUM — "Works on my machine" risk during deployment.
 - **Status**: Align DB versions (recommend 10.6 LTS) and unify entrypoint logic.
 - **Root Cause**: **Configuration Drift**. Environments were likely set up at different times or by different logical paths without strict parity enforcement.
 
 ### O-044: Inconsistent Code Headers
 - **Location**: Global (`master_plan_it/**/*.py`)
 - **Issue**: Inconsistent file header patterns. Some files (`mpit_budget.py`) use rich structured headers (FILE/SCOPO/INPUT/OUTPUT), others use standard copyright, others have none.
 - **Evidence**: `mpit_budget.py` has rich header; `mpit_budget_line.py` has standard copyright; `test_translations.py` has simple docstring.
 - **Impact**: LOW — Cosmetic/maintanability.
 - **Status**: Adopt a single standard (Rich Header for complicated Controllers, Standard for others) and enforce via linter/hook.
 - **Root Cause**: **Style Guide Missing/Ignored**. No enforced standard for file headers during development.

### O-017: devtools/verify.py references v2 reports and charts (Merged O-027)
- **Location**: `master_plan_it/devtools/verify.py` L30-43
- **Issue**: `REQUIRED_REPORTS` and `REQUIRED_DASHBOARD_CHARTS` lists contain v2 report names/charts that no longer exist or are unreliable
- **Evidence**: Reports `MPIT Baseline vs Exceptions` etc. not in codebase; Dashboard page `mpit-dashboard` validation fails
- **Impact**: HIGH — Verify script is unreliable for deployment validation
- **Status**: Code fix and full audit required
- **Root Cause**: **Technical Debt / Incomplete Migration**. Tool was created for v2 and ignored during v3 migration. References obsolete 'Baseline/Forecast' reports.

### O-018: mpit_budget_addendum.py uses 'Baseline' in validation
- **Location**: `master_plan_it/doctype/mpit_budget_addendum/mpit_budget_addendum.py` L38-48
- **Issue**: Validation logic queries for `budget_kind = 'Baseline'` as fallback — while has_column check is present, the fallback should be removed
- **Evidence**: `mpit_budget.json` has only `budget_type: Live/Snapshot`, never `budget_kind`
- **Impact**: MEDIUM — Works via has_column fallback but adds confusion
- **Status**: Code cleanup recommended
- **Root Cause**: **Transitional Logic / Defensive Coding**. fallback logic (`elif has_column budget_kind`) was left to handle a hybrid state during v2->v3 migration but was never removed.

### O-019: demo_data.py uses v2 budget_kind field
- **Location**: `master_plan_it/devtools/demo_data.py` L233-235
- **Issue**: Creates budget with `budget_kind='Forecast'` and `is_active_forecast=0` — v2 fields that don't exist in v3
- **Evidence**: `mpit_budget.json` has `budget_type: Live/Snapshot`, no `budget_kind` or `is_active_forecast`
- **Impact**: HIGH — Demo data script will fail on v3 codebase
- **Status**: Code fix required
- **Root Cause**: **Technical Debt / Incomplete Migration**. Script explicitly targets v2 schema (`budget_kind='Forecast'`) and was not updated for v3.

### O-020: dashboard_defaults.py references v2 reports
- **Location**: `master_plan_it/master_plan_it/devtools/dashboard_defaults.py` L21-27
- **Issue**: Seeds filters for v2 report names: `MPIT Plan Delta by Cost Center`, `MPIT Baseline vs Exceptions`, `MPIT Current Plan vs Exceptions`
- **Evidence**: These reports don't exist in `master_plan_it/report/`
- **Impact**: HIGH — Script will fail when calling set_filters for nonexistent charts
- **Status**: Code fix required
- **Root Cause**: **Technical Debt / Incomplete Migration**. Seeds filters for v2 report names (`MPIT Baseline vs Exceptions`) which no longer exist.

### O-021: test_budget_engine_v2.py uses v2 terminology
- **Location**: `master_plan_it/tests/test_budget_engine_v2.py` L73, L173, L196
- **Issue**: Tests use `budget_kind='Forecast'` which doesn't exist in v3 DocType
- **Evidence**: `mpit_budget.json` has `budget_type: Live/Snapshot`
- **Impact**: MEDIUM — Tests may fail or test obsolete functionality
- **Status**: Either update tests or delete if v2 behavior no longer needed
- **Root Cause**: **Technical Debt / Incomplete Migration**. Test file was written for v2 logic and never updated to v3. Only v2-specific fields are tested.

### O-022: annualization.py docstring mentions "baseline"
- **Location**: `master_plan_it/annualization.py` L7
- **Issue**: Docstring says "Handles temporal calculations for budget lines and baseline expenses"
- **Evidence**: Code no longer uses "baseline" concept
- **Impact**: LOW — Cosmetic docstring drift
- **Status**: Update docstring
- **Root Cause**: **Cosmetic Drift**. Docstring was not updated when logic was refactored to remove "baseline" concept.

## Product Logic & UX Issues

### O-045: Workspace Ambiguity - Double Entry Paths
- **Location**: Workspace "Master Plan IT"
- **Issue**: Both "Projects" and "Planned Items" are top-level shortcuts. Users may overlook that Projects require Planned Items to affect the budget, leading to confusion or double-counting attempts.
- **Impact**: MED — UX friction and potential data entry errors.
- **Proposed Fix**: Clarify that Planned Items are child-elements. Consider removing "Projects" from main list if Planned Items are the primary entry point for costs.

### O-046: Poor Feedback Loop on Budget Refresh
- **Location**: `mpit_budget.js` / "Refresh from Sources" button.
- **Issue**: Clicking "Refresh" triggers a background process that only updates the timeline comment. No toast/popup informs the user of success.
- **Impact**: LOW — User uncertainty ("Did it work?").
- **Proposed Fix**: Add `frappe.msgprint` or toast upon completion of the server method.

### O-047: Terminology Inconsistency (Allocation vs Allowance)
- **Location**: Global UI / Docs
- **Issue**:
  - "Allocation": Dead term from v2 (see O-004), still visible in UI.
  - "Allowance": Used for Snapshot manual lines, but easily confused with "Exception" or "Unplanned".
- **Impact**: LOW — Cognitive load.
- **Proposed Fix**: Remove "Allocation" entirely. Rename "Allowance" to "Manual Adjustment" or "Snapshot Reserve" for clarity.

## Discrepancies vs Documentation (Codebase Analysis 2026-01-04)

### O-048: Missing README documentation for DocTypes
- **Location**: All DocType folders (e.g., `mpit_planned_item/`)
- **Issue**: Missing `README.md` in DocType folders explaining the model, as suggested by best practices for App Development.
- **Reference**: Frappe Framework Docs -> App Development -> Documentation.
- **Impact**: LOW — Documentation gap for developers.
- **Status**: Add README.md to key data models first.

### O-049: Dashboard persistence uses localStorage instead of User Defaults
- **Location**: `mpit_dashboard.js` L213 (`localStorage.setItem`)
- **Issue**: Dashboard filters are saved to browser `localStorage` (`mpit.dashboard.filters`), creating inconsistency across devices and sessions.
- **Reference**: Frappe Framework Docs -> Desk -> Guides -> Storing User Preferences.
- **Impact**: MEDIUM — User experience inconsistency.
- **Status**: Migrate to `frappe.defaults` or `frappe.db.set_value` on MPIT Settings.

### O-050: Inefficient `doc.save()` in loop (Budget Refresh)
- **Location**: `budget_refresh_hooks.py` L171 (inside `realign_planned_items_horizon`)
- **Issue**: `doc.save(ignore_permissions=True)` is called inside a loop over `MPIT Planned Item`. This triggers a full write + events for every item, which is O(N) and potentially slow.
- **Reference**: Frappe Framework Docs -> Database -> Optimization.
- **Impact**: MEDIUM — Performance bottleneck on refresh if many items need realignment.
- **Status**: Refactor to use `bulk_update` or move to background job if triggers are essential.

### O-051: Unnecessary Raw SQL for Simple Queries
- **Location**: `budget_refresh_hooks.py` L73
- **Issue**: Uses `frappe.db.sql` for a simple `SELECT ... FROM ... WHERE docstatus < 2`.
- **Reference**: Frappe Framework Docs -> Internal API -> Database (Recommend ORM `frappe.get_all` for simple queries).
- **Impact**: LOW — Readability / Maintainability.
- **Status**: Convert to `frappe.get_all`.

### O-052: Empty `public/js` directory
- **Location**: `master_plan_it/public/js/`
- **Issue**: Directory is empty, implying no global JS customizations. If the app requires global styling/scripts (e.g. for branding), they are missing or misplaced.
- **Reference**: Frappe Framework Docs -> Asset Bundling.
- **Impact**: LOW — Verification needed (is this intentional?).

### O-053: Excessive and Fragile Raw SQL Strings in Reports
- **Location**: `report/mpit_budget_diff.py` (L64), `report/mpit_renewals_window.py` (L56)
- **Issue**: Extensive use of f-strings and manual clause building (`{" AND ".join(where)}`) inside `frappe.db.sql`. While parameters like `%(budget)s` are used in some places, the *structure* of the query is built dynamically with python strings, which is a known pattern for SQL Injection if not carefully managed.
- **Reference**: Frappe Framework Docs -> Database -> Safe Database Calls.
- **Impact**: HIGH — Security risk and maintenance burden.
- **Status**: Refactor reports to use Frappe Query Builder (pypika) for safe, dynamic query construction.

### O-054: Redundant N+1 Query in Budget Refresh Hooks (Confirmed)
- **Location**: Same as O-050 but distinct pattern: `realign_planned_items_horizon` loads doc for every item.
- **Description**: Reconfirmed during deep scan.

---

## ✅ Resolved Issues (This Session)

| ID | Description | Resolution |
|----|-------------|------------|
| ADR 0011 | Obsolete v2 doc | Deleted |
| how-to/04 | v2 budget cycle | Deleted |
| how-to/06 | Stale contract fields | Deleted |
| how-to/09 | Duplicate bootstrap | Deleted |
| how-to/10 | Generic epic notes | Deleted |
| how-to/11 | User Preferences ref | Deleted |
| 03-workflows | v2 terminology | Updated to Live/Snapshot |
| 02-roles | v2 terminology | Updated |
| 10-money-vat L11 | User Prefs ref | → MPIT Settings |
| 09-naming-title | User Prefs section | → MPIT Settings |
| field-help-text | User Prefs section | Removed |
| O-003 | field-help-text-report.md | File deleted |
