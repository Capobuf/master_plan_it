# How to bootstrap from scratch (no existing install)

This guide assumes you already have a working Frappe bench (docker or bare-metal).

## 1) Create the app scaffold
From `frappe-bench`:
```bash
bench new-app master_plan_it
```

## 2) Apply the overlay + spec from this techkit
Extract this zip. Then:
```bash
export BENCH_PATH=/path/to/frappe-bench
./starter-kit/tools/bench/apply_overlay.sh
```

## 3) Create a dev site
Example:
```bash
bench new-site mpit.local --admin-password admin --mariadb-root-password admin
```

## 4) Install the app
```bash
bench --site mpit.local install-app master_plan_it
```

## 5) Sync MPIT specs into the DB and export docfiles
```bash
bench --site mpit.local execute master_plan_it.devtools.sync.sync_all
bench --site mpit.local migrate
bench --site mpit.local clear-cache
```

## 6) Bootstrap tenant defaults and verify
```bash
bench --site mpit.local execute master_plan_it.devtools.bootstrap.run --kwargs '{"step":"tenant"}'
bench --site mpit.local execute master_plan_it.devtools.verify.run
```

## 7) Run tests
```bash
bench --site mpit.local set-config allow_tests true
bench --site mpit.local run-tests --app master_plan_it
```
