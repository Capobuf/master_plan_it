# Migrazione alla documentazione minima e agli Spec Kit verticali

Questo pacchetto è stato preparato contro:

- repository: `Capobuf/master_plan_it`;
- branch: `laravel-replatform`;
- HEAD verificato: `8f0f5660b409b562d354589d9e00012f31df8ef2`;
- data: 2026-08-09.

Non applicare automaticamente il pacchetto se il branch è avanzato: prima va riverificato il delta.

## 1. Preflight

Eseguire dalla root del repository:

```bash
git switch laravel-replatform
git status --short

BASE=8f0f5660b409b562d354589d9e00012f31df8ef2
test "$(git rev-parse HEAD)" = "$BASE" || {
  echo "HEAD changed: stop and review the documentation reset against the new branch state."
  exit 1
}

test -z "$(git status --porcelain)" || {
  echo "Working tree is not clean."
  exit 1
}

git switch -c docs/vertical-spec-reset
```

## 2. Rimuovere il vecchio grafo documentale

Conservare `.specify/scripts`, `.specify/templates` e le skill Spec Kit: servono per le future
feature. La costituzione precedente viene sovrascritta dal nuovo file del pacchetto.

```bash
git rm -r docs/replatform

git rm -r \
  specs/001-platform-foundation \
  specs/002-master-data \
  specs/003-expense-domain \
  specs/004-contracts-and-projects \
  specs/005-reporting-and-analytics \
  specs/006-data-migration-and-operations \
  specs/007-tenancy-and-access-control

git rm -f --ignore-unmatch \
  docs/api/v1-capability-matrix.md \
  .codex/orchestration-plan.md
```

`docs/api/v1-capability-matrix.md` viene ritirato perché duplicava lo stato di route/OpenAPI e
richiedeva manutenzione manuale. `docs/STATUS.md` ne sostituisce soltanto la funzione di stato,
mentre `docs/api/openapi-v1.yaml` resta il contratto API.

Non eseguire:

```bash
rm -rf .specify
rm -rf .agents/skills
```

## 3. Estrarre il pacchetto

Tenere lo ZIP fuori dalla root Git e quindi:

```bash
unzip -o /percorso/master-plan-it-doc-spec-reset.zip -d .
```

Il pacchetto sovrascrive `README.md` e `.specify/memory/constitution.md` e aggiunge la nuova
documentazione e gli Spec Kit verticali.

## 4. Controlli documentali

```bash
test -f docs/api/openapi-v1.yaml
test -d .specify/scripts
test -d .specify/templates
test -f .specify/memory/constitution.md

git diff --check
git status --short
```

Verificare che non restino riferimenti eseguibili al vecchio grafo:

```bash
git grep -nE 'docs/replatform|specs/00[1-7]-|T00[1-7]-|FR-00[1-7]-' -- . \
  ':(exclude)MIGRATION.md' || true
```

Un risultato va esaminato, non cancellato automaticamente: potrebbe essere evidenza o codice
ancora valido.

## 5. Verifiche runtime disponibili

Solo in un ambiente già configurato con le dipendenze richieste:

```bash
php artisan route:list --path=api/v1
composer test:static
composer test:accounting
composer test:application
```

Frontend:

```bash
cd frontend
npm ci
npm run build
npm run lint
```

Questi comandi non sono dichiarati superati dal presente pacchetto: devono essere realmente eseguiti
nel repository dopo l'applicazione.

## 6. Commit

Dopo revisione del diff e delle verifiche applicabili:

```bash
git add -A
git commit -m "docs: reset documentation and vertical specs"
```
