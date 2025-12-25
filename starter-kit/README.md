# Master Plan IT - Starter Kit (Frappe v15)

This starter kit contains:
- updated documentation (root `/docs`)
- a deterministic "no-GUI" dev workflow using `bench execute` + JSON specs
- an overlay you can copy into a `bench new-app master_plan_it` scaffold

## Typical bootstrap
1) Setup a bench (docker or native).
2) Create the app scaffold:
   `bench new-app master_plan_it`
3) Apply overlay/spec:
   `BENCH_PATH=/path/to/frappe-bench ./starter-kit/tools/bench/apply_overlay.sh`
4) Create site + install app
5) Sync + migrate:
   `bench --site <site> migrate`
   `bench --site <site> migrate`

IMPORTANT: the DocType specs in `starter-kit/spec/doctypes/*.json` are **minimal placeholders**.
They are meant to validate the workflow (no UI), not to finalize the data model.


## Examples
We included real exported `.json` examples from your earlier `vcio_budget` app under:
`starter-kit/examples/vcio_budget_json/`
Use them as a reference for field keys and required properties.
