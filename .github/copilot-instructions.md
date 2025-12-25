# GitHub Copilot instructions — Master Plan IT (MPIT)

Breve: MPIT è un'app Frappe Desk multi-tenant (1 sito = 1 cliente). Tutte le modifiche a metadata (DocType, Workflow, Report, Dashboard) sono tracciate su filesystem con il workflow nativo **file-first**; nessun pipeline custom di sync/import.

## Big picture (why & topology) 🔧
- Architettura: app Frappe (backend bench) + nginx frontend (vedi `compose.yml`). Tenant = sito Frappe (vCIO lavora su molti siti). (docs: `docs/explanation/01-architecture.md`)
- Policy: **Solo Desk** (nessun sito pubblicato come portal/Website Users). Nessuna build JS/CSS personalizzata - **non** aggiungere pipeline di asset.

## Dove cercare le sorgenti di verità 📁
- App: `apps/master_plan_it/`
- **Metadata standard esportati (source of truth):** `apps/master_plan_it/master_plan_it/master_plan_it/{doctype,report,workflow,dashboard,dashboard_chart,number_card,master_plan_it_dashboard,workspace,print_format}/`
  - Modifica direttamente questi JSON; sono la base per il sito e per i deploy.
  - ⚠️ Non creare cartelle duplicate a livello superiore (drift).
- **Python logic (calcoli/validazioni):** `apps/master_plan_it/master_plan_it/doctype/*/mpit_*.py`
- Devtools/entrypoint: `apps/master_plan_it/master_plan_it/devtools/` (`sync.py`, `bootstrap.py`, `verify.py`)
- Hooks: `apps/master_plan_it/master_plan_it/hooks.py`
- Docs operative: `docs/how-to/09-docker-compose-notes.md`, `docs/how-to/08-user-guide.md`
- **Spec:** `apps/master_plan_it/master_plan_it/spec/` è documentazione di design; non usarla per import.

## Comandi essenziali (esempi concreti) ✅
- Applicare metadata/versioning al sito (standard Frappe):
  - `bench --site <site> migrate`
  - `bench --site <site> clear-cache`
- Esporta le Customizations da Desk quando necessario (non lasciare modifiche solo in DB).
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
  - ✅ Modifica i file esportati sotto `apps/master_plan_it/master_plan_it/master_plan_it/...`
  - ✅ Se usi Desk (solo skeleton o Customizations su DocTypes non di proprietà), esegui subito **Export Customizations** verso la cartella canonica.
  - ❌ Nessun pipeline custom di import/sync_all; nessuna cartella di metadata duplicata fuori dal modulo canonico.
- **Logica di business (Python):** Modifica i file `.py` nei doctype (es: `mpit_budget.py`)
- Evitare rinomi di oggetti (DocType/Module) dopo averli creati: rompe percorsi/fixture.
- Fixture: non esportare senza filtri; il progetto mantiene fixtures selettive in `master_plan_it/fixtures/`.

> Nota: leggi `AGENT_INSTRUCTIONS.md` prima di eseguire modifiche automatizzate — lì sono elencati i "Non-negotiables" e la procedura standard.

## Checklist rapida per ogni modifica a metadata 🧭
1. **Modifica i file esportati** in `apps/master_plan_it/master_plan_it/master_plan_it/...` (o esporta le Customizations da Desk in quelle cartelle).
2. **Modifica Python** se necessario in `apps/master_plan_it/master_plan_it/doctype/*/mpit_*.py`.
3. Esegui `bench --site <site> migrate` e `bench --site <site> clear-cache`.
4. Esegui `bench --site <site> execute master_plan_it.devtools.verify.run`.
5. Esegui test: `bench --site <site> run-tests --app master_plan_it`.
6. Committa i file canonici in Git.
7. Aggiorna `docs/` o aggiungi ADR se c'è una decisione architettonica.
