# Docker Compose notes (troubleshooting & fixes)

Queste note raccolgono le correzioni tecniche applicate per far partire l’ambiente docker e l’app MPIT.

## Immagine custom con Node
- Base image: `${FRAPPE_IMAGE}:${FRAPPE_TAG}` ma costruiamo `master_plan_it-frappe:local` con `Dockerfile.frappe` per installare `nodejs`/`npm` (necessari a `socketio.js`).
- PYTHONPATH esteso all’interno dell’immagine: `/home/frappe/frappe-bench/apps:/home/frappe/frappe-bench/apps/frappe:/home/frappe/frappe-bench/apps/erpnext:/home/frappe/frappe-bench/apps/master_plan_it`.
- Entrambi i servizi `frappe` e `frontend` usano l’immagine buildata.

## Compose/env da ricordare
- `INSTALL_APPS=master_plan_it` (passato al servizio `frappe`) così l’app viene installata al bootstrap.
- Healthcheck MariaDB usa `mariadb-admin ping -p${DB_ROOT_PASSWORD}` (non `mysqladmin`).
- Volume `data/sites`: deve essere scrivibile da UID 1000. Se partono errori “Permission denied” o loop di creazione sito, eseguire `chown -R 1000:1000 data/sites data/logs` (o ricreare `data/sites` vuoto).

## Entry point container (`config/mpit-entrypoint.sh`)
- Crea `sites/common_site_config.json` se manca.
- Genera `sites/apps.txt` e `sites/apps_path.txt` dai contenuti di `apps/`.
- Exporta `PYTHONPATH` (per includere l’app montata) e forza `developer_mode=1` sul sito per consentire la generazione di DocType standard.
- Se `sites/<SITE_NAME>` non esiste: `bench new-site` con `ADMIN_PASSWORD`, `DB_ROOT_PASSWORD`, installa eventuali `INSTALL_APPS`, setta connessioni Redis/DB, e opzionalmente `RUN_MIGRATE_ON_START`.

## Reset rapido in caso di loop
1) Fermare lo stack: `docker compose down`
2) Ripulire storage (solo se ok perdere dati): `rm -rf data/db data/sites`
3) Ricreare cartelle e permessi: `mkdir -p data/sites && chown -R 1000:1000 data/sites`
4) Build e avvio: `docker compose build frappe frontend && docker compose up -d`

## Applicare l’app dopo modifica codice/spec
Da container `frappe`:
```bash
bench --site $SITE_NAME execute master_plan_it.devtools.sync.sync_all
bench --site $SITE_NAME migrate
bench --site $SITE_NAME clear-cache
# opzionale ma consigliato: bootstrap (workspace/ruoli) + verify
bench --site $SITE_NAME execute master_plan_it.devtools.bootstrap.run --kwargs '{"step":"tenant"}'
bench --site $SITE_NAME execute master_plan_it.devtools.verify.run
```

## Workspace MPIT
- La workspace “Master Plan IT” è creata dal bootstrap (`bench --site <site> execute master_plan_it.devtools.bootstrap.run --kwargs '{"step":"tenant"}'`).
- Se non visibile, rieseguire bootstrap e `bench --site <site> clear-cache`.
