# GitHub Copilot instructions — Master Plan IT (MPIT)

Breve: MPIT è un'app Frappe Desk multi-tenant (1 sito = 1 cliente). L'obiettivo principale è mantenere tutte le modifiche a metadata (DocType, Workflow, Report, Dashboard) tracciabili su filesystem e applicarle in modo idempotente tramite gli strumenti di `bench` e gli script di `master_plan_it`.

## Big picture (why & topology) 🔧
- Architettura: app Frappe (backend bench) + nginx frontend (vedi `compose.yml`). Tenant = sito Frappe (vCIO lavora su molti siti). (docs: `docs/explanation/01-architecture.md`)
- Policy: **Solo Desk** (nessun sito pubblicato come portal/Website Users). Nessuna build JS/CSS personalizzata - **non** aggiungere pipeline di asset.

## Dove cercare le sorgenti di verità 📁
- App: `apps/master_plan_it/`
- **Spec per sync deterministico:** `apps/master_plan_it/master_plan_it/spec/` (doctypes, workflows, reports, dashboards)
  - ⚠️ **IMPORTANTE:** Modifica SEMPRE i file spec qui, NON i file esportati in `doctype/*/mpit_*.json`
  - Flusso: `spec/` → [sync_all] → DB → [export_to_files] → `doctype/*/mpit_*.json`
- **Python logic (calcoli/validazioni):** `apps/master_plan_it/master_plan_it/doctype/*/mpit_*.py`
- Devtools/entrypoint: `apps/master_plan_it/master_plan_it/devtools/` (`sync.py`, `bootstrap.py`, `verify.py`)
- Hooks: `apps/master_plan_it/master_plan_it/hooks.py`
- Docs operative: `docs/how-to/09-docker-compose-notes.md`, `docs/how-to/08-user-guide.md`

## Comandi essenziali (esempi concreti) ✅
- Sincronizzare gli spec (idempotente):
  - `bench --site <site> execute master_plan_it.devtools.sync.sync_all`
- Applicare metadata/versioning al sito:
  - `bench --site <site> migrate`
  - `bench --site <site> clear-cache`
- Bootstrap tenant / workspace / ruoli:
  - `bench --site <site> execute master_plan_it.devtools.bootstrap.run --kwargs '{"step":"tenant"}'`
- Verifica post-apply:
  - `bench --site <site> execute master_plan_it.devtools.verify.run`
- Test (smoke/unit):
  - `bench --site <site> run-tests --app master_plan_it` (vedi `starter-kit/overlay/.../tests/test_smoke.py`)

## Docker / ambiente locale 🐳
- File principale: `compose.yml` (usa `Dockerfile.frappe`).
- Note importanti:
  - Impostare `INSTALL_APPS=master_plan_it` per installare automaticamente l'app in bootstrap.
  - Non montare `./data/apps` vuoto: monta solo `./apps/master_plan_it` per sviluppo (vedi commento in `compose.yml`).
  - `mpit-entrypoint.sh` crea site se mancante, forza `developer_mode=1` e può eseguire `migrate` se `RUN_MIGRATE_ON_START=1`.
- Reset rapido: `docker compose down && rm -rf data/db data/sites && mkdir -p data/sites && chown -R 1000:1000 data/sites && docker compose up -d` (vedi `docs/how-to/09-docker-compose-notes.md`).

## Convezioni di sviluppo e sicurezza ⚠️
- Non aggiungere custom JS/CSS o pipeline frontend.
- **Cambiamenti di metadata:** 
  - ✅ Modifica gli spec in `apps/master_plan_it/master_plan_it/spec/`
  - ❌ NON modificare i file JSON esportati in `doctype/*/mpit_*.json` (saranno sovrascritti)
  - Dopo le modifiche: `sync_all` → `migrate` → `clear-cache`
- **Logica di business (Python):** Modifica i file `.py` nei doctype (es: `mpit_budget.py`)
- Evitare rinomi di oggetti (DocType/Module) dopo averli creati: rompe percorsi/fixture.
- Fixture: non esportare senza filtri; il progetto mantiene fixtures selettive in `master_plan_it/fixtures/`.

> Nota: leggi `AGENT_INSTRUCTIONS.md` prima di eseguire modifiche automatizzate — lì sono elencati i "Non-negotiables" e la procedura standard.

## Checklist rapida per ogni modifica a metadata 🧭
1. **Modifica gli spec** in `apps/master_plan_it/master_plan_it/spec/doctypes/*.json` (o workflows/reports)
2. **Modifica Python** se necessario in `apps/master_plan_it/master_plan_it/doctype/*/mpit_*.py`
3. Esegui `bench --site <site> execute master_plan_it.devtools.sync.sync_all`
4. Esegui `bench --site <site> migrate` e `bench --site <site> clear-cache`
5. Esegui `bench --site <site> execute master_plan_it.devtools.verify.run`
6. Esegui test: `bench --site <site> run-tests --app master_plan_it`
7. Committa sia gli spec che i file esportati in Git
6. Aggiorna `docs/` o aggiungi ADR se c'è una decisione architettonica.