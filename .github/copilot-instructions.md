# GitHub Copilot Instructions — Master Plan IT (MPIT)

MPIT is a **Frappe Desk v15 app** for multi-tenant budgeting, contracts, and projects. One site = one client (tenant). All metadata changes are tracked on the filesystem using Frappe's native **file-first workflow** — no custom sync/import pipeline.

> Always read `AGENT_INSTRUCTIONS.md` before making automated changes. It lists non-negotiables and the standard workflow.

## Architecture

- **App repo** (`master_plan_it/`): Frappe app — Python controllers + exported metadata JSON. App root is this repo; inside a bench the path is `/home/frappe/frappe-bench/apps/master_plan_it`.
- **Deploy repo** (`../master-plan-it-deploy/`): Docker Compose stack — MariaDB + Redis + single Frappe container + nginx.
- **Desk-only policy**: No published website/portal. No custom JS/CSS. No frontend build pipeline — ever.
- **Multi-tenant**: vCIO manages multiple client sites; each site is a separate Frappe site on the same bench.
- Docs: `docs/explanation/01-architecture.md`

### Core DocTypes

| DocType | Purpose |
|---|---|
| `MPIT Budget` | Live/Snapshot budget with workflow (Draft → Proposed → Approved) |
| `MPIT Budget Line` | Line items within a budget; auto-refreshed by Budget Engine v3 |
| `MPIT Budget Addendum` | Submitted adjustments to approved budgets |
| `MPIT Contract` | Vendor contracts with renewal tracking |
| `MPIT Contract Term` | Recurring cost terms within a contract |
| `MPIT Planned Item` | Projects/one-off items linked to a budget |
| `MPIT Actual Entry` | Exceptions/allowances (submitted doc) |
| `MPIT Cost Center` | Hierarchical cost centers (root: "All Cost Centers") |
| `MPIT Year` | Fiscal year; current + next created by install hooks |
| `MPIT Settings` | Singleton; naming series prefixes, VAT defaults |
| `MPIT Vendor` | Vendor master |
| `MPIT Project` | Project planning with workflow |

### Budget Engine v3

The engine auto-refreshes Live budgets when validated sources change. Triggered via `doc_events` in `hooks.py` → `budget_refresh_hooks.py`. Rolling horizon = current year + next year. Snapshot budgets are never auto-refreshed.

### Key Python modules

- `master_plan_it/amounts.py` — bidirectional qty/price/monthly/annual calculations
- `master_plan_it/annualization.py` — temporal overlap calculations for fiscal years
- `master_plan_it/budget_refresh_hooks.py` — Budget Engine v3 event handlers
- `master_plan_it/mpit_defaults.py` — reads MPIT Settings singleton; exposes `@frappe.whitelist()` methods for JS
- `master_plan_it/naming_utils.py` — naming series helpers
- `master_plan_it/setup/install.py` — `after_install`/`after_sync`/`after_migrate` hooks (idempotent bootstrap)
- `master_plan_it/devtools/verify.py` — deterministic post-apply checks

## Source-of-truth locations

| What | Where |
|---|---|
| Exported metadata JSON | `master_plan_it/master_plan_it/{doctype,report,workflow,dashboard,dashboard_chart,number_card,master_plan_it_dashboard,workspace,print_format}/` |
| Python logic | `master_plan_it/master_plan_it/doctype/*/mpit_*.py` |
| Install/bootstrap hooks | `master_plan_it/setup/install.py` |
| Fixtures (roles only) | `master_plan_it/fixtures/role.json` |
| App hooks | `master_plan_it/hooks.py` |
| Translations | `master_plan_it/translations/it.csv` |

⚠️ **Never** create metadata folders at `master_plan_it/doctype/`, `master_plan_it/report/`, etc. (outer level). The canonical path is always the inner `master_plan_it/master_plan_it/` module folder. A regression test (`test_no_forbidden_metadata_paths.py`) enforces this.

## Commands

### Apply changes to a site
```bash
bench --site <site> migrate
bench --site <site> clear-cache
```

### Run all tests
```bash
bench --site <site> run-tests --app master_plan_it
```

### Run a single test file
```bash
bench --site <site> run-tests --app master_plan_it --module master_plan_it.master_plan_it.tests.test_smoke
```

### Post-apply verification
```bash
bench --site <site> execute master_plan_it.devtools.verify.run
```

### Export Desk customizations back to canonical path
```bash
bench --site <site> export-fixtures --app master_plan_it
# For Dashboard Charts/Sources (export individually):
bench --site <site> export-json "Dashboard Chart" "<Name>" <canonical-path>
```

## Docker / local environment

- **Compose file**: `../master-plan-it-deploy/compose.yml` (builds `Dockerfile.frappe` in the deploy repo)
- The app repo is bind-mounted into the container at `/home/frappe/frappe-bench/apps/master_plan_it`
- Set `RUN_MIGRATE_ON_START=1` in env to auto-migrate on container start
- When creating a site in Docker, always add `--no-mariadb-socket` to `bench new-site`

**Full reset** (from deploy repo):
```bash
docker compose down && rm -rf data/db data/sites && mkdir -p data/sites && chown -R 1000:1000 data/sites && docker compose up -d
```

→ See `docs/how-to/09-docker-compose-notes.md` for details.

## Development conventions

### Metadata changes (file-first workflow)
1. Edit exported JSON directly under `master_plan_it/master_plan_it/...`
2. If you used Desk for skeleton/non-owned DocTypes, immediately **Export Customizations** back to the canonical path
3. Run `bench migrate` + `clear-cache`
4. Never use a custom `sync_all` or spec-import pipeline

### Python controllers
- All controller files follow the naming pattern `mpit_*.py` (e.g., `mpit_budget.py`)
- Keep logic in controllers idempotent where possible
- Whitelisted server methods for JS calls live in `mpit_defaults.py` or the relevant controller

### Translations (i18n)
- Source: `master_plan_it/translations/it.csv` (3-column CSV: source, translation, context)
- Python: `_("text")` | JS: `__("text")` | Jinja: `{{ _("text") }}`
- Use literal strings only — no variables, no concatenation; use `{0}` positional placeholders
- → See `docs/reference/12-i18n.md` for full rules

### Fixtures
- Always export with filters — never a full unfiltered export
- Fixtures ship MPIT roles only (`role.json`)

### Naming
- Never rename a DocType or module after creation — it breaks file paths and fixtures

### Documentation
- Docs use structured frontmatter (`type`, `updated`) and follow the standard in `DOCS_STANDARD.md`
- Add an ADR (`docs/adr/`) for any architectural decision
- Authoritative workflow reference: `docs/reference/06-dev-workflow.md`

## Change checklist

1. Edit metadata JSON in `master_plan_it/master_plan_it/...` (or export Customizations from Desk)
2. Edit Python in `master_plan_it/master_plan_it/doctype/*/mpit_*.py` if needed
3. `bench --site <site> migrate` + `bench --site <site> clear-cache`
4. `bench --site <site> execute master_plan_it.devtools.verify.run`
5. `bench --site <site> run-tests --app master_plan_it`
6. Commit canonical files
7. Update `docs/` or add ADR for architectural decisions
