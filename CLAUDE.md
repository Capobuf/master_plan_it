# CLAUDE.md

Read `AGENT_INSTRUCTIONS.md` in full before any task. All rules, non-negotiables, workflow, and the template prompt are defined there.

## Project summary

**Master Plan IT (MPIT)** — Frappe v16 app for budgeting, contracts, and project allocation.  
Multi-tenant (1 site = 1 client). Native Desk only, no custom JS/CSS.  
Stack: Python 3 / Frappe v16 · MariaDB 10.8 · Redis 6.2 · Docker Compose

## Quick command reference

```bash
# Install from git
bench get-app master_plan_it https://github.com/Capobuf/master-plan-it.git
bench --site <site> install-app master_plan_it
bench --site <site> migrate

# Apply changes (dev)
bench --site <site> migrate && bench --site <site> clear-cache

# Run tests
pytest master_plan_it/tests/

# Format code (line length 100, configured in pyproject.toml)
black master_plan_it/
```

## Key reference docs

- `AGENT_INSTRUCTIONS.md` — all agent rules and task workflow
- `docs/reference/06-dev-workflow.md` — authoritative dev workflow detail
- `docs/how-to/00-bootstrap-from-scratch.md` — environment setup
- `docs/how-to/01-apply-changes.md` — apply procedure reference
- `docs/adr/` — architectural decisions
- `master-plan-it-deploy/README.md` — Docker prod/dev setup and build
