# Reference: Known gaps / missing examples

Last updated: 2025-12-21

This project intentionally avoids Desk UI for creation. To keep the generator deterministic,
we need **canonical examples** (exported JSON) for every standard object we plan to generate.

## Already available (from your `vcio_budget.zip`)
We included exported examples under `starter-kit/examples/vcio_budget_json/` for:
- DocType JSON
- Query Report JSON
- Workspace JSON
- Fixtures examples (unfiltered; used only as structure reference)

## Still missing to finalize V1 automation
We still need at least one real exported JSON for:
1) **Dashboard Chart / Number Card** examples from real data (currently covered by MPIT defaults, add more if needed)
2) A Workspace example with **charts** configured exactly as we want (roles/shortcuts already covered)

### How to obtain them (once you have a bench)
Create a minimal object in any dev site (even temporary) and then export it:
- Dashboards: same.

Workflows for Budget and Budget Amendment are defined in `spec/workflows/` and applied via `sync_all`. Dashboards are now provisioned (`Master Plan IT Overview`), but you can add more chart/card examples as needed.
