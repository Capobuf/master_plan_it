# How-to: Bootstrap from scratch

1) Create a new site in your bench and install the app (`bench --site <site> install-app master_plan_it`).
2) Ensure canonical metadata exists under `master_plan_it/master_plan_it/`.
3) Apply files to the site with standard commands:
   - `bench --site <site> migrate`
   - `bench --site <site> clear-cache`
4) Export any Desk customizations back into the canonical folder immediately after making them.
5) Install hooks create MPIT Settings, MPIT Year (current + next), and seed the root Cost Center (“All Cost Centers”); verify from Desk after migrate.

Reports/charts available after migrate:
- MPIT Current Plan vs Exceptions
- MPIT Baseline vs Exceptions
- MPIT Monthly Plan vs Exceptions
- MPIT Projects Planned vs Exceptions
- MPIT Plan Delta by Category (chart)

## Docker compose (prod) – promemoria rapido
- Stack minimale: `db` (MariaDB 10.6) + `redis` + `backend` (gunicorn + workers/scheduler/socketio in un solo container) + `frontend` (nginx). Compose: `master-plan-it-deploy/compose.prod.yaml`, env: `master-plan-it-deploy/prod.env`.
- Immagine applicativa: build locale `master_plan_it-frappe:prod` da `Dockerfile.frappe` (base `frappe/erpnext:${FRAPPE_IMAGE_TAG}`) per avere `node` a bordo.
- Mount: volumi `sites` e `logs` + bind `../master-plan-it` in backend/frontend. Rete `frappe` interna + `proxy` esterna con alias `mpit-frontend`.
- Backend entrypoint (da compose) attende db/redis, popola `common_site_config.json` (db/redis/socketio + dns_multitenant), rigenera `apps.txt`, avvia gunicorn/socketio/2 worker + scheduler.

## Creare un nuovo sito sullo stack prod
Esegui i comandi dal container backend già avviato:
```bash
# 1) Crea il sito (usa la password root DB di prod.env)
docker compose -f master-plan-it-deploy/compose.prod.yaml --env-file master-plan-it-deploy/prod.env \
  exec backend bash -lc "cd /home/frappe/frappe-bench && \
    bench new-site budget.zeroloop.it \
      --mariadb-user-host-login-scope='%' \
      --admin-password 'ADMIN_STRONG' \
      --db-root-username root \
      --db-root-password \"$MYSQL_ROOT_PASSWORD\" \
      --no-mariadb-socket"

# 2) Installa l'app
docker compose -f master-plan-it-deploy/compose.prod.yaml --env-file master-plan-it-deploy/prod.env \
  exec backend bash -lc "cd /home/frappe/frappe-bench && bench --site budget.zeroloop.it install-app master_plan_it"

# 3) Hostname e default_site (se serve)
docker compose -f master-plan-it-deploy/compose.prod.yaml --env-file master-plan-it-deploy/prod.env \
  exec backend bash -lc "cd /home/frappe/frappe-bench && \
    bench --site budget.zeroloop.it set-config host_name budget.zeroloop.it && \
    bench set-config -g default_site budget.zeroloop.it"

# 4) (facoltativo) Aggiorna sites.txt per comodità
docker compose -f master-plan-it-deploy/compose.prod.yaml --env-file master-plan-it-deploy/prod.env \
  exec backend sh -lc "printf \"%s\\n\" mpit.localhost budget.zeroloop.it > /home/frappe/frappe-bench/sites/sites.txt"

# 5) Riavvia backend per rileggere config
docker compose -f master-plan-it-deploy/compose.prod.yaml --env-file master-plan-it-deploy/prod.env restart backend
```
Il reverse proxy esterno deve inoltrare `Host: <site>` verso `mpit-frontend:8080` (rete `proxy`). Password admin e `MYSQL_ROOT_PASSWORD` vanno sostituite con valori sicuri in `prod.env` prima del bootstrap.
