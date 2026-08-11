# Feature 008 — Platform operations

Status: `PROPOSED TARGET — extracted from approved legacy requirements; not fully implemented at baseline`

## Problema

Il dominio di autenticazione, Tenant, user e role è operativo, ma mancano superfici complete per
impostazioni piattaforma/Tenant, audit, notifiche e alcune operazioni di account. Queste funzioni
devono essere disponibili senza creare un secondo pannello amministrativo o esporre segreti.

## Obiettivo

Completare end-to-end le operazioni amministrative e personali mancanti, dal frontend React alle
API Laravel e alle relative autorizzazioni.

## User stories

### US-008-01 — Cambiare la propria password

Un utente autenticato cambia la propria password dall'interfaccia. Non viene introdotto un
forgot-password self-service.

### US-008-02 — Gestire audit retention

Administrator visualizza e modifica il retention period globale, default 24 mesi, e può avviare
l'operazione di retention con conferma rinforzata quando la riduzione può eliminare eventi più
vecchi.

### US-008-03 — Gestire la quota allegati Tenant protetta

Administrator, su un Tenant selezionato, modifica la attachment quota, default 2 GiB.

I normali ruoli Tenant non possono ricevere questa ability protetta. Le impostazioni generali del
Tenant corrente, incluso `deletion_reason_required`, appartengono alla Feature 021 e non sono
ridefinite qui.

### US-008-04 — Consultare audit

Un actor autorizzato consulta audit del proprio Tenant. Administrator può consultare audit Tenant
o globale. Audit export non è incluso.

### US-008-05 — Consultare notifiche

Un actor autorizzato consulta le database notification pertinenti. L'eventuale fallimento
dell'email resta visibile e non viene nascosto da retry silenziosi.

### US-008-06 — Vista operativa globale

Administrator, fuori dal contesto economico Tenant, vede stato Tenant, user count, last activity,
alert operativi, rinnovi e errori operativi disponibili, senza aggregazioni economiche cross-Tenant
e senza behavioral telemetry.

### US-008-07 — Reset emergenza Administrator

L'operatore dispone di un comando interattivo esplicito per reimpostare la password del global
Administrator, con input nascosto e invalidazione delle sessioni.

## Requisiti

- FR-008-001: il cambio password utente deve richiedere sessione attiva, current password valida e
  conferma della nuova password; password e hash non devono entrare in audit, log o response.
- FR-008-002: non deve esistere self-service password recovery per tenant user.
- FR-008-003: `audit_retention_months` deve avere default 24 e range 1–120 mesi.
- FR-008-004: solo Administrator può modificare la retention globale.
- FR-008-005: ridurre la retention richiede conferma rinforzata; la retention elimina solo audit
  event eleggibili e non business record, revision identity o BudgetVersion.
- FR-008-006: attachment quota deve essere un valore intero esatto non negativo; zero è valido e
  non deve cancellare payload esistenti.
- FR-008-007: non deve esistere un massimo prodotto della quota inferiore alla rappresentabilità
  tecnica persistita.
- FR-008-008: le impostazioni generali delegabili del Tenant corrente sono fuori scope e restano
  definite dalla Feature 021.
- FR-008-009: la quota è modificabile solo da Administrator tramite una ability protetta per il
  Tenant esplicitamente selezionato.
- FR-008-010: audit view deve essere paginata/minimizzata, tenant-scoped e priva di segreti o file
  payload.
- FR-008-011: audit export è fuori scope.
- FR-008-012: le notification devono essere permission-scoped e non richiedere Redis, WebSocket o
  queue worker permanente.
- FR-008-013: il global overview non deve contenere economics aggregati tra Tenant o behavioral
  telemetry.
- FR-008-014: il comando di reset emergenza deve essere interattivo, non contenere default
  credentials e invalidare le sessioni dell'Administrator.

## Acceptance

- stesso Tenant/permission funziona; permission mancante e altro Tenant falliscono senza disclosure;
- la riduzione retention non modifica alcun business record;
- quota zero blocca soltanto operazioni future che richiedono nuovi payload;
- una modifica della quota non elimina payload o evidenze pregresse;
- password/segreti non compaiono in audit, notification, log applicativi o response;
- il global overview resta operativo e non economico.

## Fuori scope

- audit export;
- forgot-password self-service;
- behavioral telemetry;
- cross-Tenant economics;
- worker permanente, Redis o WebSocket;
- nuovo sistema generico di settings.
