# Agent Instructions — Master Plan IT (MPIT)

Single source of truth for LLM agent rules. Read this file in full before any task.

## Non-negotiables

- No custom JS/CSS or frontend build pipeline. Native Frappe Desk only.
- Metadata lives only in `master_plan_it/master_plan_it/` — never in the parent folder. `test_no_forbidden_metadata_paths.py` enforces this.
- No custom sync/import pipeline. Use standard `bench migrate`.
- Changes must be idempotent where applicable.
- Never overwrite standard Frappe fixtures or export/import standard system records.
- Never `git pull` or `pip install` inside a running production container. App code is baked into the image at build time.

## Source of truth

| Type | Path |
|------|------|
| Metadata (DocType, Workflow, Report, Dashboard, Workspace, Print Format) | `master_plan_it/master_plan_it/{doctype,report,workflow,dashboard,dashboard_chart,number_card,workspace,print_format}/` |
| Python controllers | `master_plan_it/master_plan_it/doctype/*/mpit_*.py` |
| Hooks | `master_plan_it/hooks.py` |
| Bootstrap | `master_plan_it/setup/install.py` (after_install/after_sync) |
| Fixtures | `master_plan_it/fixtures/` (filtered exports only; roles shipped) |
| Translations | `master_plan_it/master_plan_it/locale/main.pot` + `master_plan_it/master_plan_it/locale/it.po` |

## Development model

Dev uses Docker with a bind-mount of the app repo:

```bash
# From master-plan-it-deploy/
docker compose -f compose.dev.yml up -d
```

App repo must be a sibling directory (`../master_plan_it`). Host edits appear in the container immediately.

## Production model

Prod uses a pre-built image with the app baked in at build time. No source mounts.

```bash
# Upgrade: pull new image → recreate containers → migrate
docker compose -f compose.prod.yml --env-file prod.env pull
docker compose -f compose.prod.yml --env-file prod.env up -d --force-recreate
docker compose -f compose.prod.yml exec backend bench --site <site> migrate
```

See `master-plan-it-deploy/README.md` for build and first-run instructions.

## Apply changes (development)

```bash
bench --site <site> migrate      # schema, fixtures, patches
bench --site <site> clear-cache  # always after migrate
# Hard refresh browser: Ctrl+F5
```

| Change type | Command |
|-------------|---------|
| DocType / Workflow / Workspace JSON | `migrate` + `clear-cache` |
| Python logic | `clear-cache` (+ restart web worker if needed) |
| Translations (`locale/*.po`) | `bench compile-po-to-mo --app master_plan_it --locale it --force` + `clear-cache` |
| Fixtures | `migrate` + `clear-cache` |

## Standard task flow

1. Read all relevant source files in full before making any change.
2. Edit canonical JSON under `master_plan_it/master_plan_it/...` and/or Python controllers.
3. If you used Desk for a skeleton or non-owned DocType customization: immediately Export Customizations to the canonical path.
4. Apply: `bench --site <site> migrate && bench --site <site> clear-cache`.
5. Verify (see below).
6. Commit canonical files.
7. Update docs or add an ADR only if an architectural decision changed.

## Required outputs for each task

- Exact diff of changed files (path + content delta).
- Apply command specific to the change type.
- Verification step: what to check in DB or test output.
- ADR if and only if an architectural decision was made.

## Verification

```bash
# Confirm object exists in DB
bench --site <site> console
>>> frappe.db.get_value("DocType", "<Name>", "modified")

# Run tests (must pass, especially path regression)
pytest master_plan_it/tests/
pytest master_plan_it/tests/test_no_forbidden_metadata_paths.py
```

## Safety checks

- Do not rename DocTypes or modules after creation (breaks file paths and fixtures).
- Export fixtures with filters only; never export unfiltered Frappe standard objects.
- In Docker dev, verify the bind mount is active before assuming edits are visible.
- Always use `--no-mariadb-socket` when creating a new site in Docker.

## Translations (i18n)

Source: `master_plan_it/master_plan_it/locale/main.pot` (template) + `master_plan_it/master_plan_it/locale/it.po` (Italian)
Usage: Python `_("text")` · JS `__("text")` · Jinja `{{ _("text") }}`
See `docs/reference/12-i18n.md` for full rules.

## Template prompt for new tasks

```
Role: operational agent on the MPIT codebase.
Read AGENT_INSTRUCTIONS.md in full first.
Read all files relevant to this task before making any change.
Do not invent. Do not assume. If something is not proven by the code, say so.
Output: exact diff, apply command, verification step.
Keep changes minimal and native Frappe.
Task: <describe task here>
```
