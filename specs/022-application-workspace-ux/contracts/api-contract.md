# Contratto API di Programma

**Versione**: Design target per `/api/v1`  
**Ambito**: Delta richiesto dalle Slice Verticali; gli endpoint correnti non menzionati restano
baseline finché la relativa Slice non li modifica.

## Regole Comuni

- Autenticazione sessione Sanctum; Bearer Token rifiutati come nella baseline.
- Ogni endpoint applicativo richiede Utente Attivo, Tenant Context, Tenant Attivo e autorizzazione
  server-side.
- Un identificatore appartenente a un altro Tenant non deve rivelare l'esistenza del record.
- Gli Importi sono stringhe decimali canoniche con due cifre; nessun numero JSON floating point è
  autorevole.
- Ogni mutazione complessa accetta `correlation_id` UUID per idempotenza e `lock_version` per
  concorrenza ottimistica quando modifica un aggregato esistente.
- Le risposte di validazione identificano il campo o la Riga responsabile e non eliminano i valori
  già inseriti nel client.
- Preview e Impact View non persistono e applicano le stesse regole della mutazione finale.
- Le Risorse economiche espongono sempre `basis: net|gross` accanto ai Totali sintetici.

## Errori di Dominio Stabili

| Codice | HTTP | Significato UX |
|---|---:|---|
| `VALIDATION_FAILED` | 422 | Correggere i campi evidenziati |
| `STALE_VERSION` | 409 | Il documento è cambiato; ricaricare o confrontare |
| `COVERAGE_INCOMPLETE` | 422 | Quote Plafond diverse dall'intero Importo della Riga |
| `PLAFOND_OVERRUN_CONFIRMATION_REQUIRED` | 409 | Mostrare impatto e chiedere motivazione |
| `BUDGET_STATE_CONFLICT` | 409 | Operazione incompatibile con Preparazione/Approvato/Finale |
| `BUDGET_DEPENDENCIES_EXIST` | 409 | Annullamento o Riapertura bloccati dagli eventi elencati |
| `YEAR_MISMATCH` | 422 | Data incompatibile con l'Anno; proporre il flusso assistito previsto |
| `RESTORE_DEPENDENCY_MISSING` | 409 | Prima ripristinare o sostituire le dipendenze indicate |
| `TENANT_BOUNDARY_VIOLATION` | 404 | Nessuna informazione sul record esterno al Tenant |
| `SOURCE_ALREADY_GENERATED` | 409 | Generazione contrattuale o automatica già applicata |

## Slice 1 — Workspace Annuale e Spesa Autorevole

### Impostazioni Tenant

```text
GET  /api/v1/tenant-settings
PUT  /api/v1/tenant-settings
```

Il payload espone almeno:

```json
{
  "economic_basis": "net",
  "economic_basis_locked_at": null,
  "revision_limit": 10,
  "default_vat_rate": "22.00"
}
```

Un cambio Base dopo il blocco restituisce `BUDGET_STATE_CONFLICT`. Il client non deduce il blocco
dalla sola presenza di Budget: usa il campo esplicito e gestisce comunque l'errore server.

### Spese

```text
GET    /api/v1/expenses?year=2026&search=&filters[...]&sort=&page=
POST   /api/v1/expenses
GET    /api/v1/expenses/{expense}
PUT    /api/v1/expenses/{expense}
DELETE /api/v1/expenses/{expense}
POST   /api/v1/expenses/preview
```

Il documento contiene intestazione, Righe, Riga Previsionale Corrente, Totali nelle tre componenti,
Base ufficiale, origine, Anno di Competenza, Date reali, Allegati e azioni consentite. I campi
`state`, `closure_outcome`, `approved_current` e testi `actual` non fanno parte del contratto target.

`POST /preview` valida calcoli, Date e appartenenza annuale senza salvare; restituisce Totali e
conseguenze previste. Le normali create/update rimangono utilizzabili senza una preview precedente.

## Slice 2 — Plafond e Copertura Ripartita

Le Righe accettano:

```json
{
  "coverage_allocations": [
    {
      "plafond_expense_id": 41,
      "net": "100.00",
      "vat": "22.00",
      "gross": "122.00",
      "position": 1
    }
  ],
  "overrun_note": null
}
```

La mutazione ricalcola le tre componenti server-side; i valori inviati non possono forzare Totali
incoerenti. Per assistere la ripartizione:

```text
POST /api/v1/expenses/{expense}/rows/{row}/coverage-preview
GET  /api/v1/plafonds?year=&cost_center_id=
GET  /api/v1/plafonds/report?year=&cost_center_id=&plafond_ids[]=
```

La preview restituisce Quote proposte, Capienza per Plafond, Copertura Prevista, Consumato, Residuo,
Sforamento e `requires_overrun_note`. Una Copertura incompleta non viene mai accettata come parziale.

## Slice 3 — Budget Proposto e Approvazione

```text
GET  /api/v1/budget?year=
GET  /api/v1/budget/{planningYear}/approval-preview
POST /api/v1/budget/{planningYear}/approve
POST /api/v1/budget/{planningYear}/approval/{approval}/annul
GET  /api/v1/budget/{planningYear}/approvals
```

`approve` non riceve un elenco parziale di Importi da sovrascrivere. Riceve Data, eventuale Nota,
Lock Version e Correlation ID; il server ricostruisce e fotografa l'intero Budget Proposto mostrato
dalla preview. Per impedire approvazioni su dati cambiati, preview e conferma condividono un token o
hash di composizione verificato server-side.

La risposta Budget separa:

- `proposed` durante la Preparazione;
- `approved_snapshot` e `planned` dopo l'Approvazione;
- `effective`, `extra`, `rectifications` e differenze;
- Base e timestamp della fotografia.

## Slice 4 — Budget Durante l'Anno e Chiusura

```text
GET  /api/v1/budget/{planningYear}/closing-preview
POST /api/v1/budget/{planningYear}/close
POST /api/v1/budget/{planningYear}/reopen
GET  /api/v1/budget/{planningYear}/rectifications
GET  /api/v1/budget/{planningYear}/rectifications/{rectification}
```

La preview elenca Spese senza Effettivi, Progetti Aperti, Contratti, Plafond e azioni contestuali,
ma usa `can_close: true` anche quando rimangono segnalazioni non bloccanti. Dopo la Chiusura, ogni
mutazione economica restituisce la Rettifica prodotta e richiede la Nota quando previsto.

## Slice 5 — Progetti Pluriennali

```text
GET  /api/v1/projects?year=&scope=current|path
POST /api/v1/projects/{project}/move-preview
POST /api/v1/projects/{project}/move
POST /api/v1/projects/{project}/continuation-preview
POST /api/v1/projects/{project}/continue
POST /api/v1/projects/{project}/close
GET  /api/v1/projects/{project}/path
```

Move e Continue sono azioni distinte. La preview dichiara Spese coinvolte, Effettivi rilevati,
Importi suggeriti, Coperture incompatibili e collocazione nel Budget di destinazione. La Fase del
Progetto rimane un normale campo descrittivo e non sostituisce queste azioni.

## Slice 6 — Contratti e Scadenziario

```text
GET  /api/v1/contracts?year=&search=&filters[...]&page=
POST /api/v1/contracts
GET  /api/v1/contracts/{contract}
PUT  /api/v1/contracts/{contract}
POST /api/v1/contracts/{contract}/generation-preview
POST /api/v1/contracts/{contract}/synchronize
POST /api/v1/contracts/{contract}/cease
GET  /api/v1/contract-schedule?from=&to=&vendor_id=&cost_center_id=&status=
```

La generazione è idempotente per Source Key. La risposta Scadenziario contiene Data, Periodicità,
Importo, preavviso, stato (`to_generate`, `quote_generated`, `effective_generated`, `excluded`) e link
a Contratto, Termine, Spesa e Riga.

Per un Contratto Mensile, `synchronize` crea o aggiorna una sola Spesa per Anno e inserisce una Riga
per Scadenza. Per un Rinnovo Annuale crea una Riga interamente attribuita all'Anno della Data, anche
se il periodo di servizio attraversa l'anno successivo.

## Slice 7 — Composizione Annuale e Avvio Storico

```text
GET  /api/v1/planning-years/{year}/composition
POST /api/v1/planning-years/{year}/composition/preview
POST /api/v1/planning-years/{year}/composition/apply
POST /api/v1/planning-years/{year}/activate-historical
```

La composizione presenta una lista di decisioni per elemento; non esegue riporti automatici opachi.
Ogni scelta è identificata e idempotente. Per gli Anni Storici, `activate-historical` abilita il
Previsto Ricostruito secondo la classificazione Extra corrente.

## Slice 8 — Cestino e Recupero

```text
GET    /api/v1/trash?entity_type=&year=&deleted_from=&deleted_to=&search=&page=
GET    /api/v1/trash/{entityType}/{id}
POST   /api/v1/trash/{entityType}/{id}/restore-preview
POST   /api/v1/trash/{entityType}/{id}/restore
DELETE /api/v1/trash/{entityType}/{id}
```

L'Eliminazione Definitiva richiede permesso distinto e conferma. Il tipo di entità è una whitelist
server-side, non un nome di classe accettato dal client. Restore Preview elenca dipendenze e impatto
economico; il Restore non riattiva ricorsivamente altre entità.

## Slice 9 — Cronologia delle Versioni

Contratto uniforme applicato alle entità abilitate:

```text
GET  /api/v1/{resource}/{id}/revisions
GET  /api/v1/{resource}/{id}/revisions/{revision}
POST /api/v1/{resource}/{id}/revisions/{revision}/restore-preview
POST /api/v1/{resource}/{id}/revisions/{revision}/restore
```

Il dettaglio Snapshot restituisce il documento completo renderizzabile, un diff strutturato rispetto
alla Versione Corrente, autore, data e operazione. Il Restore non ricrea Allegati eliminati e lo
dichiara nella preview.

## Slice 10 — Report Comparativi e Dashboard

```text
GET /api/v1/report-series/options?year=
GET /api/v1/reports/comparison?left[type]=&left[year]=&right[type]=&right[year]=&dimension=
GET /api/v1/reports/comparison/detail?...filtri contestuali...
GET /api/v1/dashboard?year=
```

La risposta del Confronto include entrambe le Serie, KPI, Dimensioni, Progressione Mensile e
metadati di Drill-Down. I Totali e il Dettaglio devono riconciliare; il client non ricalcola le
formule dai punti del grafico.

## Slice 11 — Registri, Guida e Anagrafiche

```text
GET /api/v1/view-preferences/{viewKey}
PUT /api/v1/view-preferences/{viewKey}
GET /api/v1/guide-preference
PUT /api/v1/guide-preference
```

`viewKey` usa una whitelist di viste note. Le azioni massive ricevono identificatori espliciti o una
query firmata dal server con esclusioni esplicite; prima della mutazione il server ricalcola gli
elementi ancora autorizzati e idonei.

## Slice 12 — Amministrazione Tenant e Self-Hosting

```text
GET  /api/v1/tenants
POST /api/v1/tenants
GET  /api/v1/tenant-settings/scheduler
GET  /api/v1/platform/maintenance-status
```

La risposta Scheduler contiene il comando Cron copiabile e, solo quando esiste evidenza applicativa,
l'ultima esecuzione e l'esito. In assenza usa `status: unverifiable`, mai un booleano inferito.

## Compatibilità e Rimozioni

Il prodotto è Greenfield: Backend e Frontend della singola Slice possono cambiare atomicamente senza
versionare dati reali preesistenti. Al completamento della Slice interessata devono essere rimossi:

- payload e filtri `open/closed` della Spesa;
- `closure_outcome` sulla Spesa;
- `approved_current` e mutazioni `variation`;
- singolo `funded_plafond_expense_id` senza Quota;
- terminologia UI `Actual` e `Forecast`;
- Data contrattuale sintetica `01-01` usata al posto delle Scadenze reali.
