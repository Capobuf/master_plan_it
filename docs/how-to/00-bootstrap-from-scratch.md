# How-to: Bootstrap from scratch

1. Create a new site in your bench and install the app (`bench --site <site> install-app master_plan_it`).
2. Ensure canonical metadata exists under `master_plan_it/master_plan_it/`.
3. Apply files to the site with standard commands:
   - `bench --site <site> migrate`
   - `bench --site <site> clear-cache`
4. Export any Desk customizations back into the canonical folder immediately after making them.
5. Install hooks create MPIT Settings and MPIT Year (current + next); verify from Desk after migrate.

---

## Docker – Produzione

### Struttura compose

| File | Scopo |
| --- | --- |
| `compose.dev.yml` | Sviluppo locale (build + bind mount app) |
| `compose.prod.yml` | Produzione (immagine pre-buildata, nessun build) |

L'immagine di produzione va buildata dalla pipeline CI con `Dockerfile.frappe`  
e pushata su un registry prima del deploy.  
Il server di produzione non fa mai `docker build`.

### Bootstrap nuovo ambiente

```bash
# 1. Clona il repo deploy sul server
git clone https://github.com/Capobuf/master-plan-it-deploy.git
cd master-plan-it-deploy

# 2. Crea il file segreti da template
cp prod.env.example prod.env
# Edita prod.env: imposta CUSTOM_IMAGE, CUSTOM_TAG, DB_ROOT_PASSWORD

# 3. Avvia db e redis
docker compose -f compose.prod.yml --env-file prod.env up -d db redis

# 4. Crea il sito (sostituisci i valori tra < >)
docker compose -f compose.prod.yml --env-file prod.env \
  run --rm backend bash -lc "
    cd /home/frappe/frappe-bench
    bench new-site <site.example.com> \
      --mariadb-user-host-login-scope='%' \
      --admin-password '<ADMIN_PASSWORD>' \
      --db-root-username root \
      --db-root-password \"\$DB_ROOT_PASSWORD\" \
      --install-app master_plan_it \
      --no-mariadb-socket \
      --set-default
  "

# 5. Avvia tutti i servizi
docker compose -f compose.prod.yml --env-file prod.env up -d
```

Il reverse proxy esterno deve inoltrare `Host: <site>` verso `mpit-frontend:8080`  
sulla rete Docker `proxy`.

### Upgrade ambiente esistente

```bash
cd master-plan-it-deploy

# 1. Aggiorna CUSTOM_TAG in prod.env con il nuovo tag, poi pull
docker pull $(grep CUSTOM_IMAGE prod.env | cut -d= -f2):$(grep CUSTOM_TAG prod.env | cut -d= -f2)

# 2. Ricrea i container applicativi
docker compose -f compose.prod.yml --env-file prod.env \
  up -d --force-recreate backend frontend

# 3. Migra ogni sito
docker compose -f compose.prod.yml --env-file prod.env \
  exec backend bash -lc "bench --site <site.example.com> migrate"

# 4. Verifica
docker compose -f compose.prod.yml --env-file prod.env \
  exec backend bash -lc "bench --site <site.example.com> doctor"
```

### Backup minimo

```bash
docker compose -f compose.prod.yml --env-file prod.env \
  exec backend bash -lc "
    cd /home/frappe/frappe-bench
    bench --site <site.example.com> backup --with-files
  "
# Backup salvato in: sites/<site>/private/backups/
```

Copia i backup fuori dal volume prima di operazioni rischiose:

```bash
docker cp mpit-backend:/home/frappe/frappe-bench/sites/<site>/private/backups/ ./backups/
```

### Restore minimo

```bash
docker compose -f compose.prod.yml --env-file prod.env \
  exec backend bash -lc "
    cd /home/frappe/frappe-bench
    bench --site <site.example.com> restore \
      sites/<site>/private/backups/<backup-file>.sql.gz \
      --mariadb-root-password \"\$DB_ROOT_PASSWORD\"
  "
```

### Aggiungere un secondo sito (multi-tenant)

```bash
docker compose -f compose.prod.yml --env-file prod.env \
  exec backend bash -lc "
    cd /home/frappe/frappe-bench
    bench new-site <site2.example.com> \
      --mariadb-user-host-login-scope='%' \
      --admin-password '<ADMIN_PASSWORD>' \
      --db-root-username root \
      --db-root-password \"\$DB_ROOT_PASSWORD\" \
      --install-app master_plan_it \
      --no-mariadb-socket
  "
```

Il reverse proxy deve poi inoltrare `Host: site2.example.com` allo stesso `mpit-frontend:8080`.

