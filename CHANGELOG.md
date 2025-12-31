# CHANGELOG — Master Plan IT

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> Note: starter-kit overlays, spec folders, backups, and custom preflight have been removed in the current repo. Bootstrap now relies on install hooks + fixtures; historical entries remain for context.

## [0.1.0] - 2025-12-22

### Added - EPIC MPIT-E01 (Phases 1-6)

#### Phase 1: User Preferences
- **DocType:** `MPIT User Preferences` with per-user VAT defaults, naming series, print options
- **Helper Module:** `master_plan_it/mpit_user_prefs.py` with functions:
  - `get_or_create(user)` — Get or create preferences for user
  - `get_default_vat_rate(user)` — Get default VAT rate
  - `get_default_includes_vat(user)` — Get default includes VAT flag
  - `get_budget_series(user)` — Get Budget naming series
  - `get_project_series(user)` — Get Project naming series
  - `get_show_attachments_in_print(user)` — Get attachments print preference
- **Workspace Integration:** Added "User Preferences" shortcut to Master Plan IT workspace
- **Precision:** Set VAT Rate field to `precision: 2` for consistent % display

#### Phase 2: Title Field UX
- **MPIT Budget:** Added `title_field: "title"` and `show_title_field_in_link: 1`
- **MPIT Project:** Added `title_field: "title"` and `show_title_field_in_link: 1`
- **UX Improvement:** Link fields now show human-readable titles instead of IDs

#### Phase 3: Naming Automation
- **MPIT Budget:** Implemented `autoname()` method with pattern `BUD-{YYYY}-{NN}`
  - Uses `MPIT User Preferences.budget_prefix` (default: `BUD-`)
  - Year extracted from `MPIT Budget.year` field
  - Sequential numbering per year via `frappe.utils.data.getseries()`
- **MPIT Project:** Implemented `autoname()` method with pattern `PRJ-{NNNN}`
  - Uses `MPIT User Preferences.project_prefix` (default: `PRJ-`)
  - 4-digit sequential numbering via getseries
- **Example Names:** BUD-2025-01, BUD-2025-02, PRJ-0001, PRJ-0002

#### Phase 4: Strict VAT Normalization
- **Helper Module:** `master_plan_it/tax.py` with functions:
  - `split_net_vat_gross(amount, vat_rate, includes_vat)` — Split amount into net/vat/gross
  - `validate_strict_vat(amount, vat_rate, default_vat, field_label)` — Enforce VAT required for non-zero amounts
- **DocTypes Updated (7 total):**
  - `MPIT Budget Line` — Added `vat_rate`, `amount_includes_vat`, `amount_net`, `amount_vat`, `amount_gross`
  - `MPIT Actual Entry` — Added VAT fields
  - `MPIT Contract` — Added `contract_amount_*` (net/vat/gross)
  - `MPIT Amendment Line` — Added `delta_*` (net/vat/gross)
  - `MPIT Project Allocation` — Added `planned_*` (net/vat/gross)
  - `MPIT Project Quote` — Added `amount_*` (net/vat/gross)
- **Controllers Updated (6 total):**
  - `mpit_actual_entry.py` — `_compute_vat_split()` in validate()
  - `mpit_contract.py` — `_compute_vat_split()` in validate()
  - `mpit_budget.py` — `_compute_vat_split()` for child table lines
  - `mpit_budget_amendment.py` — `_compute_vat_split()` for child table lines
  - `mpit_project.py` — `_compute_vat_split()` for allocations and quotes
- **Data Migration:** Created patch `v0_1_0/backfill_vat_fields.py` for idempotent backfill
  - Sets `vat_rate=0`, `includes_vat=0`, `net=amount`, `vat=0`, `gross=amount` for historical records
- **Reports Updated (3 total):**
  - `mpit_approved_budget_vs_actual.py` — COALESCE pattern for anti-regression
  - `mpit_current_budget_vs_actual.py` — COALESCE pattern
  - `mpit_projects_planned_vs_actual.py` — COALESCE pattern

#### Phase 5: Annualization
- **Helper Module:** `master_plan_it/annualization.py` with functions:
  - `get_year_bounds(year)` — Get calendar year start/end dates
  - `overlap_months(period_start, period_end, year_start, year_end)` — Count calendar months touched
  - `annualize(amount_net, recurrence_rule, overlap_months_count)` — Calculate annual amounts
  - `validate_recurrence_rule(recurrence_rule)` — Validate supported recurrence
- **DocTypes Updated:**
  - `MPIT Budget Line` — Added `recurrence_rule`, `period_start_date`, `period_end_date`, `annual_net`, `annual_vat`, `annual_gross`
- **Controllers Updated:**
  - `mpit_budget.py` — `_compute_lines_annualization()` for child table lines
- **Recurrence Rules:** Monthly, Quarterly, Annual, None
- **Rule A Enforcement:** Block save if period has zero overlap with fiscal year (months touched)

#### Phase 6: Professional Printing
- **Print Formats Created (2 total):**
  - `MPIT Budget Professional` (Jinja, Standard=Yes)
    - Path: `print_format/mpit_budget_professional.{json,html}`
    - Features: header, info grid, lines table (category, vendor, net/VAT/gross, recurrence), totals
    - User preferences: conditional attachments list based on `show_attachments_in_print`
  - `MPIT Project Professional` (Jinja, Standard=Yes)
    - Path: `print_format/mpit_project_professional.{json,html}`
    - Features: header, info grid, allocations table, quotes table, totals per section
- **Report HTML Templates Created (4 total):**
  - `mpit_approved_budget_vs_actual.html` — 7-column table with variance color coding (red/green)
  - `mpit_current_budget_vs_actual.html` — 9-column table with amendment delta color coding
  - `mpit_projects_planned_vs_actual.html` — Status badges (Planning/Active/Completed/Cancelled)
  - `mpit_renewals_window.html` — Urgency badges (Expired/Urgent/Soon/Normal)
- **Styling:** Bootstrap-inspired classes, embedded CSS, no custom frontend (ADR 0006 compliance)
- **Import:** Print Formats imported via `import_file_by_path()`, report templates auto-detected
- **Test Script:** `devtools/test_print.py` for automated testing of print formats

### Changed

#### Dual-Mode Controller (Phase 6)
- **MPIT Budget Controller:** `_compute_vat_split()` now supports dual mode:
  - **New flow:** If `amount_net` present → source of truth (direct calculation)
  - **Legacy flow:** If only `amount` present → split via `tax.split_net_vat_gross()`
- **Field Migration:** `amount` field marked as `hidden: 1, read_only: 1, label: "Amount (Legacy)"` in:
  - `mpit_budget_line.json`
  - `mpit_baseline_expense.json`
  - `mpit_actual_entry.json`
  - `mpit_project_quote.json` (already without reqd/in_list_view)
- **Controller Fix:** `_compute_lines_annualization()` changed from `self.fiscal_year` to `self.year`

### Documentation

#### New Documents
- **ADR 0008:** Print Formats Server-Side con Jinja (No Custom Frontend)
- **CHANGELOG.md:** This file

#### Updated Documents
- **README.md:** Added EPIC E01 completion summary, version bump to 0.1
- **docs/how-to/10-epic-e01-money-naming-printing.md:** 
  - Added Phase 6 completion section with implementation details
  - Added test results and file structure
  - Added compliance notes with ADR 0006
- **docs/reference/11-printing-v15.md:**
  - Added Section 3: Implementazione MPIT (Dec 2025)
  - Documented Jinja template structure for print formats
  - Documented microtemplating patterns for report HTML
  - Added import/sync procedures
  - Added styling guidelines and allowed Bootstrap classes
  - Added testing strategy
- **docs/reference/10-money-vat-annualization.md:**
  - Added Section 5: Dual-Mode Controller (Phase 6 Implementation)
  - Documented dual-flow VAT calculation logic
  - Documented legacy field migration
  - Documented report anti-regression COALESCE pattern
- **docs/reference/04-reports-dashboards.md:**
  - Added Report Print Formats section
  - Documented all 4 report HTML templates with features
  - Added styling guidelines

### Technical Details

#### File Structure Created
```
master_plan_it/master_plan_it/
├── mpit_user_prefs.py (Phase 1)
├── tax.py (Phase 4)
├── annualization.py (Phase 5)
├── devtools/
│   └── test_print.py (Phase 6)
├── spec/doctypes/
│   └── mpit_user_preferences.json (Phase 1)
├── patches/
│   └── v0_1_0/
│       └── backfill_vat_fields.py (Phase 4)
└── master_plan_it/
    ├── print_format/
    │   ├── mpit_budget_professional.json (Phase 6)
    │   ├── mpit_budget_professional.html (Phase 6)
    │   ├── mpit_project_professional.json (Phase 6)
    │   └── mpit_project_professional.html (Phase 6)
    └── report/
        ├── mpit_approved_budget_vs_actual/
        │   └── mpit_approved_budget_vs_actual.html (Phase 6)
        ├── mpit_current_budget_vs_actual/
        │   └── mpit_current_budget_vs_actual.html (Phase 6)
        ├── mpit_projects_planned_vs_actual/
        │   └── mpit_projects_planned_vs_actual.html (Phase 6)
        └── mpit_renewals_window/
            └── mpit_renewals_window.html (Phase 6)
```

#### Database Schema Changes
- **35+ fields added** across 7 DocTypes for VAT normalization
- **12+ fields added** for annualization (recurrence, annual amounts)
- **1 DocType added:** MPIT User Preferences
- **All migrations idempotent** via `bench migrate`

#### Test Results (Final)
```bash
# Verify checks
master_plan_it.devtools.verify.run: ALL PASSED
- 0 missing doctypes
- 0 missing roles
- 0 missing workflows
- 0 missing reports
- 0 workspace issues

# Unit tests
bench run-tests --app master_plan_it: 2/2 PASSED

# Integration test (Budget creation)
Budget: BUD-2025-04
  Line 1 (Monthly 1000 net, 22% VAT):
    annual_net: 12000.0 (1000 × 12) ✓
    annual_vat: 2640.0 (12000 × 0.22) ✓
    annual_gross: 14640.0 ✓
  Line 2 (Quarterly 500 net, 22% VAT):
    annual_net: 2000.0 (500 × 4) ✓
    annual_vat: 440.0 (2000 × 0.22) ✓
    annual_gross: 2440.0 ✓
```

### Compliance

- ✅ **ADR 0002 (Desk-Only):** All features Desk-native, no portal
- ✅ **ADR 0006 (No Custom Frontend):** Server-side rendering only, no custom JS/CSS
- ✅ **ADR 0008 (Print Formats Jinja):** All print formats versionable in git
- ✅ **Zero Drift:** All metadata in filesystem, deterministic sync
- ✅ **Idempotency:** standard migrate/clear-cache + bootstrap steps are all idempotent

### Known Issues
None. All features fully implemented and tested.

---

## [0.0.1] - 2025-12-21

### Added
- Initial V1 blueprint
- Core DocTypes: Budget, Project, Actual Entry, Contract, Vendor, Category, Year
- Workflows: Budget Workflow, Budget Amendment Workflow
- Reports: 4 Script Reports (Approved vs Actual, Current vs Actual, Projects Planned vs Actual, Renewals Window)
- Dashboard: Master Plan IT Overview
- Bootstrap helpers: `devtools/bootstrap.py`, `devtools/verify.py` (baseline handled by install hooks in current repo)
- Documentation: Diátaxis structure (tutorials, how-to, reference, explanation)
- ADRs: 0001-0007 (architecture decisions)

### Infrastructure
- Docker Compose setup (frappe, nginx, mariadb, redis)
- Development entrypoint: `mpit-entrypoint.sh`
- Git versionable metadata: all DocTypes, Workflows, Reports in filesystem

---

[0.1.0]: https://github.com/YOUR_ORG/master-plan-it/compare/v0.0.1...v0.1.0
[0.0.1]: https://github.com/YOUR_ORG/master-plan-it/releases/tag/v0.0.1
