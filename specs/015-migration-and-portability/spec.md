# Feature 015 — Legacy migration and Tenant portability

Status: `PROPOSED TARGET — not implemented`

## Problema

Il passaggio dal sito Frappe attivo e la futura portabilità di un singolo Tenant devono essere
ripetibili e riconciliabili senza accesso diretto al database legacy, merge silenziosi o selective
disaster recovery.

## Obiettivo

Fornire due flussi distinti:

1. migrazione legacy di un singolo sito Frappe verso un Tenant esistente selezionato;
2. export/import completo e controllato dei dati portabili di un singolo Tenant.

## User stories

### US-015-01 — Dry-run legacy

Administrator seleziona il Tenant target, carica un package versionato, esegue staging,
validazione, mapping, quarantine e reconciliation senza scrivere nel dominio corrente.

### US-015-02 — Apply legacy

Dopo un dry-run valido, Administrator risolve o approva esclusioni e applica in batch
dependency-ordered con stato esplicito.

### US-015-03 — Export Tenant

Administrator esporta un package completo dei dati business portabili di un singolo Tenant.

### US-015-04 — Import Tenant

Administrator importa il package in un Tenant selezionato usando lo stesso modello di staging,
dry-run, collision quarantine e reconciliation.

## Requisiti comuni

- FR-015-001: target Tenant è scelto prima del run e resta immutabile per il run.
- FR-015-002: input autorevole è package versionato con manifest, UTF-8 CSV e SHA-256 checksums.
- FR-015-003: nessun direct connection al DB Frappe di produzione è richiesto.
- FR-015-004: ogni source row conserva source type, source ID, file e line separati dalla target
  identity.
- FR-015-005: same lineage replay è idempotente.
- FR-015-006: collisione con lineage diversa, record manuale o target modificato entra in quarantine;
  mai merge, rename, reassignment o overwrite silenzioso.
- FR-015-007: dry-run non scrive business data corrente.
- FR-015-008: ogni blocker contiene source location e stable error.
- FR-015-009: apply richiede zero blocker non risolti oppure exclusion esplicitamente approvata con
  actor e reason.
- FR-015-010: apply usa batch ordinati; failure arresta i batch successivi e registra esattamente
  cosa è committed e cosa è fallito, senza dichiarare rollback globale inesistente.
- FR-015-011: reconciliation confronta file/checksum, identity/count, attachment e exact monetary
  sums sulle dimensioni approvate.
- FR-015-012: money viene interpretato come decimal string e verificato con le regole target.
- FR-015-013: planning year non calendar-year viene rifiutato/quarantinato, non normalizzato
  silenziosamente.
- FR-015-014: terminal Project/Contract/term identity resta evidence-only e non può essere
  riattivata dall'import.
- FR-015-015: permission/import non può bypassare tenant isolation o invariant del dominio target.
- FR-015-016: apply e import apply richiedono reinforced confirmation.

## Migrazione legacy

- FR-015-020: il caso verificato è un solo sito Frappe attivo che rappresenta un cliente e viene
  importato in un singolo Tenant.
- FR-015-021: non viene costruita una piattaforma generalizzata multi-site.
- FR-015-022: i legacy state `Active/Replaced/Cancelled` servono come evidenza per determinare un
  current logical record; non vengono ricreati come copie current parallele.
- FR-015-023: un legacy Actual non diventa immutabile nel target.
- FR-015-024: Project e Contract non vengono aggiunti ai totali come sorgenti monetarie separate.
- FR-015-025: allegati presenti nel package rispettano le regole della feature 011; la migrazione
  non inventa payload version storiche non presenti nella sorgente.

## Portabilità Tenant

Il package finale deve includere i dataset implementati e portabili del Tenant, inclusi quando
esistenti:

- Tenant business settings;
- user identity senza password/hash;
- tenant roles e assignments compatibili;
- planning years, Cost Center, Vendor;
- Project, Contract e term;
- Expense e row;
- generation exception e source-deletion provenance;
- operational revisions portabili;
- attachment manifest/payload version e payload;
- Scenario;
- BudgetVersion.

Deve escludere:

- audit event;
- transient notification;
- password/hash;
- session/token;
- application secret;
- global platform setting;
- dati di altri Tenant.

Le permission protette non possono essere elevate tramite role import. Gli user importati ricevono
credenziali attraverso il normale flusso Administrator.

## Acceptance

- replay identico non duplica dati;
- collisione non sovrascrive il target;
- dry-run lascia invariato il dominio;
- ogni import resta dentro il Tenant target;
- exact monetary reconciliation passa oppure il run non è accettato;
- terminal identity non torna attiva;
- package non contiene secrets/audit/other-Tenant data;
- post-apply reconciliation è obbligatoria.

## Evidenze esterne per cutover

- export reale e anomalie;
- inventario Report legacy approvato;
- hosting finale;
- backup Verified disponibile prima dell'apply production, definito dalla feature 016.

## Fuori scope

- direct DB migration;
- generalized multi-site migration service;
- selective Tenant DR restore;
- silent conflict resolution;
- XLSX come formato autorevole di import.
