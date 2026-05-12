# Repository Guidelines

- Work from the Frappe app repository root containing `setup.py`, `pyproject.toml`, `MANIFEST.in`, and `master_plan_it/`.
- Canonical metadata and controllers live under `master_plan_it/master_plan_it/`.
- Translations live in `master_plan_it/locale/main.pot` and `master_plan_it/locale/it.po`.
- Run `bench --site <site> migrate && bench --site <site> clear-cache` after schema or metadata changes.
- Run `bench --site <site> run-tests --app master_plan_it` for Frappe-native validation.
- Use native Frappe v16 metadata and server-side validation. Do not add CSS/JS workarounds or custom sync/import pipelines.
- Budget totals come only from `MPIT Expense Row` records through `MPIT Expense`. `MPIT Contract` and `MPIT Project` are context/generator records, not independent economic totals.
