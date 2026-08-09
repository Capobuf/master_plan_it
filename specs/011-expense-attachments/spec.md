# Feature 011 — Expense attachments

Status: `PROPOSED TARGET — backend capability not exposed at baseline`

## Problema

Le Expense non dispongono ancora di una capability completa di upload/download/delete con
versionamento coerente alle revisioni e quota Tenant.

## Obiettivo

Consentire allegati privati a Expense o Expense row, preservando tenant isolation, revision
restore, deduplicazione dei payload e limiti di storage senza conservare file nei log o nell'audit.

## User stories

### US-011-01 — Allegare un file

Un utente autorizzato carica un file su una Expense oppure su una singola Expense row.

### US-011-02 — Consultare e scaricare

Un utente autorizzato visualizza metadata e scarica il payload privato solo se possiede sia
l'ability sull'allegato sia l'accesso al parent.

### US-011-03 — Eliminare un allegato

La rimozione aggiorna l'active set ma conserva il payload storico necessario alle revisioni finché
la Expense esiste.

### US-011-04 — Ripristinare una revisione

Il restore di una Expense ricostruisce atomically business data e exact attachment set della
revisione.

### US-011-05 — Gestire la quota

Administrator configura la quota tramite la feature 008; upload e restore rispettano l'uso dei
payload distinti.

## Requisiti

- FR-011-001: formati ammessi: PDF, JPEG, PNG, CSV e XLSX.
- FR-011-002: dimensione massima per file: 10,485,760 byte.
- FR-011-003: file vuoto, estensione non ammessa o mismatch tra estensione e MIME rilevato dal
  server deve essere rifiutato prima della finalizzazione.
- FR-011-004: ogni attachment ha esattamente un parent corrente dello stesso Tenant: Expense oppure
  una Expense row appartenente a quella Expense.
- FR-011-005: storage e download sono privati; nessun URL pubblico permanente deve bypassare
  l'autorizzazione.
- FR-011-006: authorization richiede l'ability dell'attachment e l'accesso al parent.
- FR-011-007: ogni revisione Expense successiva all'attivazione della capability conserva un
  manifest completo dell'attachment set.
- FR-011-008: ogni manifest riferisce payload version immutabili; byte invariati riusano la stessa
  payload version.
- FR-011-009: prima di abilitare il primo upload, tutte le revisioni Expense data-only preesistenti
  devono ricevere un manifest vuoto verificato; non va inventato attachment history.
- FR-011-010: la quota conta ogni payload version distinta non purgata una sola volta.
- FR-011-011: quota default 2 GiB; zero è valido; nessun massimo prodotto inferiore alla
  rappresentabilità tecnica.
- FR-011-012: ridurre quota non elimina payload esistenti e blocca soltanto operazioni che
  produrrebbero nuovi byte oltre quota.
- FR-011-013: una revisione/restore data-only che riusa completamente payload esistenti resta
  possibile anche con quota zero o usage già superiore alla nuova quota.
- FR-011-014: attachment delete o Expense-row delete rimuove il file dall'active set ma conserva
  payload storici richiesti dalle revisioni finché esiste la Expense.
- FR-011-015: la cancellazione definitiva della Expense elimina tutti i payload current e storici
  della Expense; restano solo metadata minimizzati e checksum ammessi.
- FR-011-016: il restore verifica availability, checksum, type, size, authorization, Tenant e quota
  e fallisce atomically se una condizione non è soddisfatta.
- FR-011-017: audit/revision metadata non deve contenere payload inline o segreti.

## Acceptance

- altro Tenant e parent non autorizzato non rivelano metadata o file;
- upload invalido non lascia orphan temporanei o finali;
- data-only update non duplica byte;
- restore produce esattamente l'attachment set della revisione;
- quota è rispettata anche con operazioni concorrenti;
- Expense delete purga i payload e rende la Expense non ripristinabile.

## Dipendenze

- feature 008 per quota;
- feature 009 per revision restore.

## Fuori scope

- formati aggiuntivi;
- file oltre 10 MiB;
- public media library;
- attachment storage come sorgente audit;
- retention dei payload dopo permanent Expense deletion.
