#!/usr/bin/env bash
set -euo pipefail

# Apply starter-kit overlay into an existing bench app created by:
#   bench new-app master_plan_it
#
# Run this script from the root of this starter-kit zip after extracting it.

if [[ -z "${BENCH_PATH:-}" ]]; then
  echo "ERROR: Set BENCH_PATH to your frappe-bench path (e.g. /home/frappe/frappe-bench)."
  exit 1
fi

APP_DIR="$BENCH_PATH/apps/master_plan_it"
if [[ ! -d "$APP_DIR" ]]; then
  echo "ERROR: App folder not found: $APP_DIR"
  echo "Did you run: bench new-app master_plan_it ?"
  exit 1
fi

echo "Copying overlay into $APP_DIR ..."
rsync -a --delete "./overlay/master_plan_it/" "$APP_DIR/"

echo "Copying spec into $APP_DIR/spec ..."
mkdir -p "$APP_DIR/spec"
rsync -a --delete "./spec/" "$APP_DIR/spec/"

echo "DONE."
echo "Next:"
echo "  bench --site <site> install-app master_plan_it"
echo "  bench --site <site> execute master_plan_it.devtools.sync.sync_all"
echo "  bench --site <site> migrate"
