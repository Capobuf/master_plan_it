# Master Plan IT — Constitution

Version: 1.0.0
Reset date: 2026-08-09
Baseline: `laravel-replatform@8f0f5660b409b562d354589d9e00012f31df8ef2`

## 1. Documentazione permanente minima

La documentazione permanente contiene soltanto comportamento implementato verificato, vincoli
architetturali durevoli, stato sintetico e comandi operativi reali.

Le funzionalità non implementate non vengono duplicate nella documentazione permanente:
appartengono allo Spec Kit della feature attiva.

## 2. Spec Kit verticali e temporanei

Ogni feature descrive un risultato utente end-to-end. Può attraversare React, API Laravel,
dominio, persistenza e test.

Non creare feature orizzontali del tipo "solo backend", "solo frontend", "solo database" quando
il risultato utente richiede più strati.

Gli artefatti `spec.md`, `plan.md`, `tasks.md`, eventuali checklist e research appartengono alla
feature. Dopo implementazione accettata e propagazione delle sole regole permanenti nella
documentazione, la directory della feature può essere eliminata.

Non creare registri globali paralleli per task, dependency, readiness, path, comandi o traceability.

## 3. Autorità sul corrente

Per ciò che è già implementato, codice, migration, test e OpenAPI prevalgono sugli Spec Kit
ritirati.

Uno Spec Kit nuovo non deve rispecificare intere aree implementate: dichiara il baseline corrente,
le dipendenze e soltanto il delta richiesto.

## 4. Decisioni

Le decisioni di prodotto spettano al Product Owner. Nessun agent può inventare comportamento su
UX, dati, permissions, costi, rischio o semantica economica.

Le decisioni tecniche ordinarie sono autonome se rispettano:

- `docs/ARCHITECTURE.md`;
- pragmatic SOLID;
- minimo numero di componenti e dipendenze necessario;
- nessun fallback silenzioso;
- nessuna estensione dello scope non richiesta.

Un'incertezza di prodotto che cambia il risultato deve essere chiarita prima del plan.

## 5. Invarianti tecniche

- Laravel è API-only e unico business owner.
- React/TailAdmin è presentation/client code.
- Tenant isolation è obbligatoria e fail-closed.
- L'autorizzazione server non può essere delegata al client.
- Il denaro autorevole usa aritmetica decimale esatta, mai float.
- Solo Expense row correnti e non eliminate alimentano il dataset economico corrente.
- Mutazioni complesse usano Actions esplicite; non nascondere side effect economici in observer,
  model hook, Resource o JavaScript.
- Errori e fallimenti devono emergere ed essere diagnosticabili.
- Non introdurre repository, CQRS, event bus, service locator, microservizi o interfacce decorative.
- Non introdurre una seconda implementazione del motore economico.
- UI: riutilizzare TailAdmin React Free prima di creare componenti visuali paralleli.

## 6. Test e sicurezza

Ogni feature deve avere acceptance verificabile e test coerenti con la suite esistente.

Per operazioni tenant-bound coprire almeno:

- allow stesso Tenant;
- deny permission mancante;
- deny altro Tenant senza data leakage;
- Tenant/user inattivo quando applicabile;
- rollback o assenza di side effect su errore per le mutazioni.

Le modifiche economiche verificano importi con stringhe decimali esatte.

Non dichiarare test superati se non sono stati eseguiti.

## 7. Flusso

Per una nuova slice:

1. `/speckit.specify`;
2. `/speckit.clarify` se restano decisioni di prodotto ad alto impatto;
3. `/speckit.plan`;
4. `/speckit.tasks`;
5. `/speckit.analyze`;
6. `/speckit.implement` con accesso reale a codice e test;
7. verifica finale contro spec, acceptance e suite;
8. aggiornamento di OpenAPI/documentazione permanente/stato;
9. rimozione degli artefatti della feature completata quando non servono più come contratto attivo.

`/speckit.analyze` è una verifica read-only: non creare un nuovo documento storico per ogni run.
Correggere direttamente spec/plan/tasks o il codice responsabile.

## 8. Governance

Un emendamento costituzionale è richiesto solo per cambiare questi principi permanenti.
Un normale requisito di feature non è un emendamento.

Ogni emendamento richiede motivazione, impatto e approvazione del Product Owner.
