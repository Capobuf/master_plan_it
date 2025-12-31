#!/usr/bin/env bash
set -euo pipefail

export DEV_SERVER=1
export PYTHONPATH="/home/frappe/frappe-bench/apps:${PYTHONPATH:-}"

cd /home/frappe/frappe-bench

# If running as root (first hop), loosen perms on mounted volumes for dev and re-exec as frappe user.
if [ "$(id -u)" -eq 0 ] && [ "${RUN_AS_FRAPPE:-0}" != "1" ]; then
  install -d -m 0777 /home/frappe/frappe-bench/sites /home/frappe/frappe-bench/logs
  chmod -R 0777 /home/frappe/frappe-bench/sites /home/frappe/frappe-bench/logs || true
  export RUN_AS_FRAPPE=1
  exec su -s /bin/bash frappe -c "/home/frappe/frappe-bench/config/mpit-entrypoint.sh"
fi

umask 000

if [ ! -f sites/common_site_config.json ]; then
  echo "{}" > sites/common_site_config.json
fi

# apps.txt serve spesso in ambienti containerizzati
if [ ! -f sites/apps.txt ]; then
  ls -1 apps > sites/apps.txt
fi
if [ ! -f sites/apps_path.txt ]; then
  for app_dir in apps/*; do
    realpath "$app_dir"
  done > sites/apps_path.txt
fi

# Config bench verso servizi esterni (idempotente)
bench set-config -g db_host db
bench set-config -gp db_port 3306
bench set-config -g redis_cache "redis://redis:6379"
bench set-config -g redis_queue "redis://redis:6379"
bench set-config -g redis_socketio "redis://redis:6379"
bench set-config -gp socketio_port 9000

# Crea site se manca (idempotente)
if [ ! -d "sites/${SITE_NAME}" ]; then
  echo "Creating site ${SITE_NAME}..."
  APPS_ARGS=()
  if [ -n "${INSTALL_APPS:-}" ]; then
    IFS=',' read -ra APPS <<< "${INSTALL_APPS}"
    for app in "${APPS[@]}"; do
      app="$(echo "$app" | xargs)"   # trim
      [ -n "$app" ] && APPS_ARGS+=(--install-app "$app")
    done
  fi

  bench new-site \
    --mariadb-user-host-login-scope='%' \
    --admin-password="${ADMIN_PASSWORD}" \
    --db-root-username=root \
    --db-root-password="${DB_ROOT_PASSWORD}" \
    "${APPS_ARGS[@]}" \
    --set-default \
    "${SITE_NAME}"
else
  echo "Site ${SITE_NAME} already exists"
fi

# Modalità developer per permettere la generazione di DocType standard
bench --site "${SITE_NAME}" set-config developer_mode 1

# Migrazioni (opzionale)
if [ "${RUN_MIGRATE_ON_START:-0}" = "1" ]; then
  bench --site "${SITE_NAME}" migrate
fi

# Avvio processi via Procfile custom
exec honcho start -f config/mpit.Procfile
