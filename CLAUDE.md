# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Master Plan IT (MPIT)** is a Frappe v15 ERP application for business budgeting, contract management, and project allocation designed for vCIO (Virtual CIO) operations. It's a multi-tenant SaaS application with hard data isolation.

**Technology Stack:**
- Backend: Frappe Framework v15 (Python 3)
- Database: MariaDB 10.8
- Cache/Queue: Redis 6.2
- Frontend: Native Frappe Desk only (no custom JS/CSS/build pipeline)
- Deployment: Docker Compose

**Key Constraint:** Pure native Frappe Desk - explicitly **no custom JavaScript, CSS, or frontend build pipeline**.

## Architecture Principles

### Multi-Tenant Model (1 Site = 1 Client)
- Each client gets a separate Frappe site with its own database
- Hard data isolation at DB level for compliance/security
- Trade-off: Operational overhead for managing multiple sites

### Desk-Only UI
- Clients are System Users accessing native Frappe Desk
- No custom frontend, no JS/CSS build pipeline
- Full feature access via dashboards, reports, workflows, list filters

### File-First Metadata Workflow
- **Source of truth:** JSON metadata files in Git repository
- **Location:** `master_plan_it/master_plan_it/{doctype,workflow,dashboard,report,workspace,etc}/`
- **Anti-pattern:** Never create metadata in parent `master_plan_it/doctype/` (only inner folder)
- Desk UI used only for initial skeleton creation; changes exported back to JSON
- No custom import/sync pipeline; uses standard Frappe `bench migrate`

## Budget Engine v3 Architecture

### Core Concepts
- **Live Budget** (single per year): Auto-refreshed from validated contracts/planned items; not submittable
- **Snapshot Budget** (immutable): "APP-{year}-NN" naming; submit for approval; locked after submit
- **Addendum**: Inline cost center adjustments to snapshots
- **Planned Items**: Project-based allocations with spend_date, distribution, and coverage flags

### Refresh Logic
- Contracts → budget lines (only "Active", "Pending Renewal", "Renewed" status)
- Planned Items → budget lines (with monthly distribution)
- Auto-refresh triggered on `doc_events` (on_update, after_submit, on_trash) for contracts/planned items/addenda
- Horizon-aware: only refreshes current + next fiscal year
- Immutability rules: Snapshot lines read-only after submit; Live lines auto-managed

### Naming Conventions
- Budget: `{prefix}{year}-LIVE` (Live) or `{prefix}{year}-APP-{NN}` (Snapshot)
- Addendum: `ADD-{year}-{cost_center_abbr}-{####}`
- Contract: `CONTR-{NN}`
- Project: `PRJ-{NN}`
- Actual Entry: `AE-{NN}`

### Amount & VAT Handling
- Bidirectional conversions: qty × unit_price ↔ monthly/annual amounts
- Priority: unit_price > monthly_amount > annual_amount
- VAT split: gross = net × (1 + rate) or net + tax
- Monthly/annual with per-recurrence multiplier (12 for Monthly, 4 for Quarterly, 1 for Annual)

## Development Commands

### Installation & Setup

```bash
# Install on existing Frappe bench
bench get-app https://github.com/Capobuf/master_plan_it.git
bench --site <site> install-app master_plan_it
bench --site <site> migrate
bench --site <site> enable-scheduler

# Docker development environment
cd master-plan-it-deploy
docker compose -f compose.yml up -d
docker compose -f compose.yml logs -f

# Create new site (Docker)
docker compose exec frappe bash -lc \
  "bench new-site <site> --no-mariadb-socket \
   --admin-password='<PASSWORD>' \
   --db-root-password='<DB_PASSWORD>'"
```

**Important:** Always use `--no-mariadb-socket` in Docker to prevent "Access denied" errors after container restarts.

### Applying Metadata Changes

```bash
# Full sync (recommended for all changes)
bench --site <site> migrate
bench --site <site> clear-cache
# Then: hard refresh browser (Ctrl+F5)

# Targeted DocType reload (layout-only changes)
bench --site <site> reload-doctype "<DocType Name>"
bench --site <site> clear-cache
# Then: hard refresh browser
```

### Testing

```bash
# Run all tests
pytest master_plan_it/tests/

# Run specific test files
pytest master_plan_it/tests/test_budget_engine_v3_acceptance.py
pytest master_plan_it/tests/test_smoke.py
pytest master_plan_it/tests/test_reports.py
pytest master_plan_it/tests/test_vat_flag.py
pytest master_plan_it/tests/test_translations.py
pytest master_plan_it/tests/test_no_forbidden_metadata_paths.py

# Run with options
pytest -v master_plan_it/tests/                    # verbose
pytest -x master_plan_it/tests/                    # stop on first failure
pytest -k "budget" master_plan_it/tests/           # pattern matching
pytest -s master_plan_it/tests/                    # show output
```

### Code Formatting

```bash
# Format all Python files
black master_plan_it/

# Check formatting (dry-run)
black --check master_plan_it/

# Format specific file
black path/to/file.py
```

**Configuration:** Line length: 100 (defined in `pyproject.toml`)

### Useful Commands

```bash
# Interactive console
bench --site <site> console

# Clear caches
bench --site <site> clear-cache
bench --site <site> clear-website-cache

# Enable developer mode
bench --site <site> set-config developer_mode 1

# List installed apps
bench --site <site> list-apps

# Backup and restore
bench --site <site> backup
bench --site <site> restore <backup-file.sql.gz>
```

## Standard Development Workflow

### Making Metadata Changes

1. **Edit canonical metadata files:**
   - Location: `master_plan_it/master_plan_it/{doctype,report,workflow,dashboard,etc}/`
   - These JSON files are the source of truth

2. **Apply to database:**
   ```bash
   bench --site <site> migrate
   bench --site <site> clear-cache
   ```

3. **If using Desk for non-owned DocTypes:**
   - Use Desk only for initial skeleton or customizations
   - Immediately **Export Customizations** back to canonical path

4. **Hard refresh browser:** Ctrl+F5

5. **Commit to Git:**
   ```bash
   git add .
   git commit -m "description of changes"
   ```

### Change Type Reference

| Change Type | Apply Command | Then |
|------------|---------------|------|
| DocType/Workflow/Workspace JSON | `bench --site <site> migrate` | `clear-cache` + hard refresh |
| Python code | Restart web process if needed | Hard refresh |
| Translations (`translations/it.csv`) | `bench --site <site> clear-cache` | Hard refresh |
| Fixtures | `bench --site <site> migrate` | `clear-cache` |

## Critical File Locations

### Metadata (Source of Truth)
- **DocTypes:** `master_plan_it/master_plan_it/doctype/*/`
- **Workflows:** `master_plan_it/master_plan_it/workflow/*/`
- **Reports:** `master_plan_it/master_plan_it/report/*/`
- **Dashboards:** `master_plan_it/master_plan_it/dashboard/*/`
- **Workspaces:** `master_plan_it/master_plan_it/workspace/*/`
- **Print Formats:** `master_plan_it/master_plan_it/print_format/*/`

### Python Logic
- **Controllers:** `master_plan_it/master_plan_it/doctype/*/mpit_*.py`
- **Core modules:**
  - `amounts.py` - Amount/VAT calculations
  - `annualization.py` - Monthly/annual conversions
  - `budget_refresh_hooks.py` - Auto-refresh triggers
  - `mpit_defaults.py` - Settings getters
  - `naming_utils.py` - Name generation

### Configuration
- **Hooks:** `master_plan_it/hooks.py` (doc_events, scheduled jobs)
- **Install:** `master_plan_it/setup/install.py` (idempotent bootstrap)
- **Fixtures:** `master_plan_it/fixtures/role.json` (MPIT roles only)

### Key DocTypes
- **mpit_budget.py** (968 lines) - Budget LIVE/Snapshot logic, refresh, totals
- **mpit_actual_entry.py** (218 lines) - Actual spend, variance, status workflow
- **mpit_contract.py** (195 lines) - Contract master, recurrence, dating
- **mpit_planned_item.py** (208 lines) - Project allocations, distribution, horizon

## Architectural Constraints

### Non-Negotiables
1. **No custom JS/CSS** - Use only Frappe Desk native features
2. **File-first metadata** - JSON in repo is source of truth; Desk exports back
3. **No custom frontend build pipeline** - No webpack, rollup, etc.
4. **No custom sync/import pipeline** - Stay native with `bench migrate`
5. **Metadata location** - Only in `master_plan_it/master_plan_it/`, never in parent folder
6. **Idempotent install** - Hooks can be run multiple times safely

### Immutability Rules
- **Live budgets:** Cannot be submitted; lines auto-managed; manual lines blocked
- **Snapshot budgets:** Immutable after submit; only vCIO Manager can create
- **Addendum:** Submit-only state (no Draft editing)
- **Planned Items:** Immutable after submit (start_date, end_date, distribution locked)

## Auto-Refresh System

Budget refresh is automatically triggered when source documents change:

**Hooks on Contract:**
- `on_update`, `on_trash`

**Hooks on Planned Item:**
- `on_update`, `after_submit`, `on_cancel`, `on_trash`

**Hooks on Addendum:**
- `after_submit`, `on_cancel`

**Implementation:** `budget_refresh_hooks.py` enqueues async refresh of Live budgets in rolling horizon (current + next year only).

## Role-Based Access Control

| Role | Permissions |
|------|------------|
| **vCIO Manager** | Full access; can unlock Verified actuals; budgets & workflows |
| **Client Editor** | Create/edit contracts, budgets, actuals; submit workflows |
| **Client Viewer** | Read-only; reports, dashboards, exports |

## Testing Infrastructure

| Test File | Coverage |
|-----------|----------|
| `test_budget_engine_v3_acceptance.py` | Budget engine end-to-end, VAT, annualization |
| `test_smoke.py` | Basic app loading |
| `test_reports.py` | Report generation |
| `test_vat_flag.py` | VAT calculations |
| `test_translations.py` | Italian translation completeness |
| `test_no_forbidden_metadata_paths.py` | Metadata location anti-drift |

**Test runner:** Frappe's pytest integration

## Documentation Structure

- **docs/reference/** - Technical specs (roles, workflows, fixtures, naming)
- **docs/how-to/** - Procedures (bootstrap, apply changes, projects)
- **docs/adr/** - Architectural decision records (10 ADRs)
- **docs/explanation/** - Deep-dive analysis
- **AGENT_INSTRUCTIONS.md** - LLM agent rules (non-negotiables, workflow)
- **README.md** - Quick start guide

## Translations (i18n)

- **Source file:** `master_plan_it/master_plan_it/translations/it.csv`
- **Usage:**
  - Python: `_("text")`
  - JS: `__("text")`
  - Jinja: `{{ _("text") }}`
- **Apply changes:** `bench --site <site> clear-cache` + hard refresh

## Docker Environment

### Development Setup
- **File:** `master-plan-it-deploy/compose.yml`
- **App mount:** `../master-plan-it` → `/home/frappe/frappe-bench/apps/master_plan_it`
- **Data volumes:** `./data/db`, `./data/redis`, `./data/sites`, `./data/logs`
- **Services:** frappe (web+socketio+worker+scheduler), db (MariaDB), redis, frontend (nginx)

### Environment Variables
- `SITE_NAME` - Site domain
- `ADMIN_PASSWORD` - Admin password
- `DB_ROOT_PASSWORD` - Database root password
- `INSTALL_APPS=master_plan_it` - Auto-install on bootstrap
- `RUN_MIGRATE_ON_START` - Run migrate on container start
- `HTTP_PORT` - Exposed port (default: 9797)

### Quick Reset (Development)
```bash
cd master-plan-it-deploy
docker compose down
rm -rf data/db data/sites
mkdir -p data/sites
chown -R 1000:1000 data/sites
docker compose up -d
```

## Troubleshooting

### Metadata Not Applying
1. Confirm you edited the **canonical** path: `master_plan_it/master_plan_it/...`
2. Run `bench --site <site> migrate` and `bench --site <site> clear-cache`
3. Hard refresh browser (Ctrl+F5)
4. In Docker: verify bind mount points to correct app directory
5. Verify with console: `frappe.db.get_value("DocType", "<Name>", "modified")`

### Workspace Changes Not Showing
1. Use console to verify DB: `frappe.db.get_value("Workspace", "Master Plan IT", ["modified", "content"])`
2. If stale, try: `bench --site <site> import-doc <ABS_PATH_TO_JSON>`
3. Always verify DB before claiming success

### Docker Access Denied Errors
- Always use `--no-mariadb-socket` when creating sites in Docker
- This sets database user host to `%` instead of container IP
- Prevents access denied after container restarts when IPs change

## Additional Resources

- Full bootstrap guide: `docs/how-to/00-bootstrap-from-scratch.md`
- Apply changes workflow: `docs/how-to/01-apply-changes.md`
- Dev workflow reference: `docs/reference/06-dev-workflow.md`
- Architectural decisions: `docs/adr/*.md`
- Open issues: `OPEN_ISSUES.md`
- Changelog: `CHANGELOG.md`
