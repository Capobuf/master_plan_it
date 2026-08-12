# Contratto API di Programma

**Versione**: `PROPOSED TARGET` per `/api/v1`; endpoint non implementati
**Ambito**: Delta richiesto dalle Slice Verticali; gli endpoint correnti non menzionati restano
baseline finché la relativa Slice non li modifica.

## Regole Comuni

- Autenticazione sessione Sanctum; Bearer Token rifiutati come nella baseline.
- Ogni endpoint applicativo richiede Utente Attivo, Tenant Context e autorizzazione server-side.
  Un Utente Tenant viene sempre respinto quando il Tenant è inattivo. Resta preservata esattamente
  l'eccezione `VERIFIED CURRENT`: un Platform Admin con ruolo protetto, contesto Tenant
  esplicitamente selezionato e ability esatta può usare anche gli endpoint business della Slice
  023 sul Tenant inattivo. L'eccezione non è ereditabile dagli Utenti Tenant.
- Un identificatore di route/root appartenente a un altro Tenant e uno inesistente restituiscono
  la stessa status/envelope 404 `RESOURCE_NOT_FOUND`. Un identificatore di relazione nel body
  foreign Tenant e uno inesistente restituiscono la stessa 422 `VALIDATION_FAILED`, generica e
  field-safe, senza nome, conteggio o dettaglio FK del record.
- Gli input Importo/IVA sono stringhe conformi a
  `^-?(0|[1-9]\d*)(\.\d{1,2})?$`; sono rifiutati `+`, spazi, virgole, esponenti, zeri iniziali e
  numeri JSON. Zero negativo viene normalizzato a `0.00`; gli output sono stringhe canoniche con
  due cifre. Nessun float è autorevole.
- Per CRUD e preview `X-Correlation-ID` è diagnostico, non una chiave idempotente: è accettato
  soltanto se UUIDv4; un valore assente o invalido viene sostituito dal server con un nuovo UUIDv4
  restituito nella risposta. L'idempotenza è richiesta soltanto da un endpoint action che la
  dichiara esplicitamente e dispone di una chiave/deduplica di dominio. La correlation di una
  RevisionBatch non implementa replay e non è unique.
- Le mutazioni di aggregati esistenti richiedono `lock_version` per concorrenza ottimistica; essa
  non sostituisce la guardia DB annuale.
- Le risposte di validazione identificano il campo o la Riga responsabile e non eliminano i valori
  già inseriti nel client.
- Preview e Impact View non persistono e applicano le stesse regole della mutazione finale.
- Le settings API espongono `economic_basis`; le proiezioni e Risorse economiche espongono
  `basis: net|gross` accanto ai Totali sintetici. Database e PHP conservano i nomi interni
  `budget_basis` e `BudgetBasis`: non è previsto un rename globale.
- Ogni mutazione economica acquisisce, dentro la propria transazione, il lock DB stabile del
  PlanningYear Tenant-scoped. L'ordine ordinario è PlanningYear per ID tecnico crescente,
  aggregate root e Righe per ID crescente; se la mutazione deve lockare anche stato Tenant, quel
  lock precede i PlanningYear. Approva,
  Chiudi, Riapri e Annulla condividono lo stesso guard con Spese/Righe, Plafond, Extra, Rettifiche,
  bulk, Contract generation/sync, azioni Project e Cancellazioni/Ripristini. Dopo i lock il server
  ricostruisce/rivalida il dataset, stato e relazioni;
  preview hash e `lock_version` non sostituiscono questa serializzazione. Guard multi-Anno sono
  acquisiti per ID crescente. Le preview non acquisiscono né riservano la guardia. La concorrenza
  viene provata su MySQL reale.
- Il reset Greenfield distruttivo si esegue soltanto con
  `php artisan app:test-reset-greenfield`. Il comando richiede congiuntamente ambiente
  `local|testing`, driver `mysql`, host esattamente `mysql`, database nell'allowlist esatta
  `{master_plan_it_test}` e strict mode attivo. Il raw `migrate:fresh`, anche con `--env` o `--force`,
  è vietato nei runbook e nei task.

## Errori di Dominio Stabili

| Codice | HTTP | Significato UX |
|---|---:|---|
| `AUTHENTICATION_REQUIRED` | 401 | La sessione applicativa manca o non è valida |
| `ACCOUNT_INACTIVE` | 403 | L'account è inattivo; nessuna operazione applicativa consentita |
| `PERMISSION_DENIED` | 403 | Ability applicativa mancante |
| `TENANT_CONTEXT_REQUIRED` | 403 | Selezionare un Tenant valido prima di continuare |
| `TENANT_INACTIVE` | 403 | Tenant inattivo per l'Utente Tenant; resta l'eccezione Platform Admin protetta e ability-scoped |
| `VALIDATION_FAILED` | 422 | Correggere i campi evidenziati |
| `STALE_VERSION` | 409 | Il documento è cambiato; ricaricare o confrontare |
| `PLAFOND_INSUFFICIENT` | 422 | Capienza insufficiente; mantenere input e mostrare impatto |
| `BUDGET_STATE_CONFLICT` | 409 | Operazione incompatibile con Preparazione/Approvato/Chiuso |
| `BUDGET_DEPENDENCIES_EXIST` | 409 | Riapertura bloccata dalle Rettifiche successive elencate |
| `BUDGET_APPROVAL_ANNULMENT_BLOCKED` | 409 | Annullamento bloccato da Effettivi, Extra Budget, Rettifiche o Chiusure elencati |
| `YEAR_MISMATCH` | 422 | Data incompatibile con l'Anno; proporre il flusso assistito previsto |
| `RESTORE_DEPENDENCY_MISSING` | 409 | Prima ripristinare o sostituire le dipendenze indicate |
| `RESOURCE_NOT_FOUND` | 404 | Stesso codice e stessa envelope per ID inesistente o appartenente a un altro Tenant |
| `SOURCE_ALREADY_GENERATED` | 409 | Generazione contrattuale o automatica già applicata |
| `ECONOMIC_RECONCILIATION_FAILED` | 500 | Invariante server non riconciliata; nessun fallback o valore parziale |
| `CSRF_TOKEN_MISMATCH` | 419 | Sessione valida ma token CSRF assente/scaduto |
| `METHOD_NOT_ALLOWED` | 405 | Metodo o azione rimossi/non supportati |
| `RATE_LIMITED` | 429 | Troppe richieste; riprovare senza perdere gli input |
| `INTERNAL_ERROR` | 500 | Errore inatteso sanitizzato con correlation ID |

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
  "default_vat_rate": "22.00"
}
```

Un cambio Base dopo il blocco restituisce `BUDGET_STATE_CONFLICT`. Il client non deduce il blocco
dalla sola presenza di Budget: usa il campo esplicito e gestisce comunque l'errore server.
`economic_basis` è il mapping API del campo/enum interno `budget_basis`/`BudgetBasis`; la Slice 023
non rinomina globalmente database o PHP. Finché l'`ApplyBudgetApproval` corrente resta
raggiungibile, la Slice 023 lo usa come bridge: locka Tenant e PlanningYear nell'ordine comune e
valorizza `economic_basis_locked_at` alla prima Approvazione nella stessa transazione della
fotografia. Un rollback lascia invariati entrambi; la Slice 025 sostituirà il bridge.
Un PUT semanticamente invariato restituisce la rappresentazione corrente senza incrementare
`lock_version` e senza creare audit o revisione; una modifica produce esattamente
`tenant.settings.updated`.
Il campo e la UI `revision_limit` appartengono alla Slice 031 insieme all'enforcement della
retention; la Slice 023 non espone un'impostazione priva del comportamento corrispondente.

### Spese

```text
GET    /api/v1/expenses?planning_year_id=17&search=&filters[...]&sort=&page=
POST   /api/v1/expenses
GET    /api/v1/expenses/{expense}
PUT    /api/v1/expenses/{expense}
POST   /api/v1/expenses/preview
```

`DELETE /api/v1/expenses/{expense}` resta `VERIFIED CURRENT`/`MIGRATION-ONLY` e non viene modificato
dalla Slice 023 nelle semantiche applicative. Finché resta raggiungibile deve però acquisire la
stessa guardia Tenant+PlanningYear delle altre mutazioni economiche; lo stesso vale per restore e
bulk verificati correnti. Il contratto target di eliminazione, Cestino e Ripristino appartiene
esclusivamente alla Slice 030; la prima Slice non anticipa né dichiara quelle semantiche.

Il documento contiene intestazione, Righe, Riga Previsionale Corrente, Totali nelle tre componenti,
Base ufficiale, origine, Anno di Competenza, Date reali, Allegati e azioni consentite. I campi
`state`, `closure_outcome`, `approved_current` e testi `actual` non fanno parte del contratto target.
Ogni Riga può includere `notes: string|null`. Una create diretta accetta soltanto un
`planning_year_id` Tenant-scoped e attivo; update/delete/restore risolvono l'Anno già assegnato e
ne rivalidano lo stato sotto guardia. `year_label` è presentazione e `spend_date` non determina la
competenza.

Una Riga usa una sola modalità importo. `direct` riceve soltanto `entered_amount`;
`calculated` riceve insieme `quantity` e `unit_price`, non riceve `entered_amount`, e persiste il
prodotto normalizzato come `entered_amount`. Laravel usa BCMath a scala intermedia 12 e
half-away-from-zero a due decimali. Con `amount_includes_vat=false` l'importo è Netto; con `true` è
Lordo. Entrambe le direzioni conservano il segno, riconciliano `net + vat = gross` e rifiutano con
422 ogni overflow di input, prodotto o componente derivata senza clamp.

`POST /preview` valida calcoli, Date e appartenenza annuale senza salvare; restituisce Totali e
conseguenze previste. Le normali create/update rimangono utilizzabili senza una preview precedente.
Quando il payload preview contiene `expense_id`, applica l'ability update, risolve root/Righe con
scope Tenant e confronta root/row `lock_version`; una versione già stale restituisce
`STALE_VERSION`. Senza `expense_id` applica l'ability create. La preview non acquisisce la guardia
annuale né riserva stato; save ripete tutti i controlli dopo i lock.

Per path/root, missing e foreign Tenant sono identici 404. Per `planning_year_id`, `cost_center_id`,
`vendor_id`, `project_id`, `contract_id` e altri relationship ID nel body, missing e foreign Tenant
sono identici 422 field-safe. Nessun dettaglio rivela esistenza o attributi foreign Tenant.

Ogni mutazione Expense riuscita produce esattamente un evento business e l'evento infrastrutturale
`revision.batch.begin`; settings produce un solo evento business. No-op, preview e rollback
producono zero revisioni/eventi. `RevisionBatchItem` usa FK composita Tenant+batch e la correlation
della revisione non è unique né idempotente.

Documento, Registro, Budget corrente, riepilogo minimo Dashboard e Report consumano la stessa
`AnnualEconomicProjection`; la risposta della proiezione usa `basis`, mai un ricalcolo frontend o
un secondo motore.

Le colonne scaffolding necessarie alle Slice future restano `MIGRATION-ONLY` e non entrano nel
payload 023. La consolidazione rimuove soltanto lifecycle Expense e l'eventuale vincolo XOR
Project/Contract. Fino alla Slice 025, gli adapter Budget correnti/storici restano compile-safe: in
Preparazione `proposed` usa `current_planning` e `actual` usa la proiezione 023; in
Approvato/Chiuso il planned provvisorio usa soltanto il contenuto immutabile della
`ApprovalOperation` corrente e `actual` resta nella proiezione; lo storico usa soltanto snapshot
ApprovalOperation realmente presenti. Nessun adapter inventa approved/planned o transizioni
quando manca lo snapshot richiesto.

## Slice 2 — Plafond Singolo e Copertura Integrale

Le Righe accettano:

```json
{
  "funded_plafond_expense_id": 41
}
```

Il riferimento identifica l'unico Plafond dello stesso Tenant e Anno. Restano `OPEN QUESTION` sia
la compatibilità tra Centri di Costo sia la possibilità che una Riga sia insieme Extra Budget e
coperta da Plafond; la Slice 024 non può cambiare le regole correnti — Centri differenti consentiti
ed Extra/Copertura mutuamente esclusivi — senza risposta. La Riga è interamente coperta oppure non
coperta. Il server ricalcola i valori nella Base ufficiale e valida gli invarianti di capienza
applicabili prima di qualsiasi persistenza.

```text
POST /api/v1/expenses/{expense}/rows/{row}/coverage-preview
GET  /api/v1/plafonds?planning_year_id=&cost_center_id=
POST /api/v1/plafonds/{plafond}/allocation-adjustments
POST /api/v1/plafonds/{plafond}/allocation-adjustments/preview
GET  /api/v1/plafonds/report?planning_year_id=&cost_center_id=
```

La preview restituisce Allocazione, Disponibile, Importo richiesto, Copertura Prevista e Consumato;
non espone un `Residuo` duplicato. Una riduzione dell'allocazione restituisce anche le Righe che
diverrebbero invalide.
`Disponibile = Allocazione - Consumato`, dove Consumato comprende soltanto Effettivi coperti;
Stime/Preventivi coperti compongono Copertura Prevista e non prenotano Disponibile. Il blocco di
capienza si applica quando nasce o cambia un Effettivo coperto e quando l'Allocazione scende sotto
il Consumato corrente.

Capienza insufficiente restituisce `PLAFOND_INSUFFICIENT` senza persistere nulla:

```json
{
  "error": {
    "code": "PLAFOND_INSUFFICIENT",
    "details": {
      "allocated": "3500.00",
      "available": "200.00",
      "required": "500.00",
      "shortage": "300.00"
    }
  }
}
```

Gli importi sono stringhe decimali nella Base ufficiale. Il client conserva gli input e offre
aumento dell'allocazione, riduzione dell'importo, divisione in due Righe o rimozione della copertura.
Il server serializza ogni mutazione sulla combinazione `tenant + year + cost_center`, ricalcola la
capienza dopo avere acquisito i lock e applica unicità, validazione e persistenza nella stessa
transazione. Una preview è informativa e non riserva capienza.

## Slice 3 — Budget Proposto e Approvazione

```text
GET  /api/v1/budget?planning_year_id=
GET  /api/v1/budget/{planningYear}/approval-preview
POST /api/v1/budget/{planningYear}/approve
GET  /api/v1/budget/{planningYear}/approval/{approval}/annulment-preview
POST /api/v1/budget/{planningYear}/approval/{approval}/annul
GET  /api/v1/budget/{planningYear}/approvals
```

`approve` non riceve un elenco parziale di Importi da sovrascrivere. Riceve Data, eventuale Nota,
Lock Version e Correlation ID; il server ricostruisce e fotografa l'intero Budget Proposto mostrato
dalla preview. Per impedire approvazioni su dati cambiati, preview e conferma condividono un token o
hash di composizione verificato server-side. La conferma acquisisce prima il guard annuale
condiviso e ricostruisce il dataset sotto lock, così una mutazione di Riga concorrente è ordinata
prima o dopo la fotografia e non può produrre uno snapshot misto.

Nel bridge 023 la prima Approvazione corrente locka prima Tenant e poi PlanningYear e valorizza
`economic_basis_locked_at` nella stessa transazione; la Slice 025 sostituisce il bridge senza
riaprire la Base.

`annulment-preview` è read-only, riguarda solo l'Approvazione attiva e restituisce collegamenti alle
Spese o operazioni nei quattro gruppi canonici:

```json
{
  "can_annul": false,
  "blockers": {
    "actuals": [],
    "extra_budget": [],
    "rectifications": [],
    "closures": []
  }
}
```

Effettivi e Extra Budget continuano a bloccare se la Spesa/Riga è nel Cestino; una Chiusura
continua a bloccare dopo una Riapertura. Non esiste un gruppo generico aggiuntivo. `annul` richiede
sempre `note` e `lock_version`, rivalida i quattro gruppi nella stessa transazione e non considera
la preview un'autorizzazione. Con blocchi restituisce `BUDGET_APPROVAL_ANNULMENT_BLOCKED`; con lock
stale restituisce `STALE_VERSION`. Ogni errore lascia invariati Budget, Approvazione, Revisioni e
Audit.

Quando consentito, `annul` marca l'Approvazione `annulled` senza eliminarla, conserva Data,
Approvatore, contenuto e Nota, riporta il Budget in Preparazione e crea una nuova Revisione e un
evento Audit. La successiva Approvazione crea una nuova fotografia e la Base Economica rimane
bloccata dalla prima Approvazione storica. La risposta Budget separa:

- `proposed` durante la Preparazione;
- `approved_snapshot` e `planned` dopo l'Approvazione;
- `effective`, `extra`, `rectifications` e differenze;
- Base e timestamp della fotografia.

Per una nuova decisione economica dopo l'Approvazione, create/preview ricevono un intento esplicito
`forgotten_item` o `new_need`. Laravel deriva rispettivamente una Rettifica o Extra Budget e rifiuta
l'assenza dell'intento; non deduce la scelta da Data, descrizione o importo.

## Slice 4 — Budget Durante l'Anno e Chiusura

```text
GET  /api/v1/budget/{planningYear}/closing-preview
POST /api/v1/budget/{planningYear}/close
POST /api/v1/budget/{planningYear}/reopening-preview
POST /api/v1/budget/{planningYear}/reopen
GET  /api/v1/budget/{planningYear}/rectifications
GET  /api/v1/budget/{planningYear}/rectifications/{rectification}
```

La preview elenca Spese senza Effettivi, Progetti Aperti, Contratti, Plafond e azioni contestuali,
ma usa `can_close: true` anche quando rimangono segnalazioni non bloccanti. Dopo la Chiusura, ogni
mutazione economica restituisce la Rettifica prodotta e richiede la Nota quando previsto.
`close` acquisisce il guard annuale condiviso e ricostruisce la situazione da fotografare sotto lo
stesso lock usato dalle mutazioni economiche.

`reopening-preview` è read-only e restituisce `can_reopen`, la Chiusura corrente e le eventuali
Rettifiche bloccanti. `reopen` richiede sempre `note`, non elimina dati economici, rende non corrente
lo snapshot di Chiusura e crea una Revisione.

## Slice 5 — Progetti Pluriennali

```text
GET  /api/v1/projects?planning_year_id=&scope=current|path
POST /api/v1/projects/{project}/move-preview
POST /api/v1/projects/{project}/move
POST /api/v1/projects/{project}/continuation-preview
POST /api/v1/projects/{project}/continue
POST /api/v1/projects/{project}/close
GET  /api/v1/projects/{project}/path
```

Move e Continue sono azioni distinte. La preview dichiara Spese coinvolte, Effettivi rilevati,
Importi suggeriti, Coperture incompatibili e collocazione nel Budget di destinazione. La Fase del
Progetto rimane un normale campo descrittivo e non sostituisce queste azioni. `continue` crea il
nuovo Progetto e il riferimento al precedente, ma non chiude l'origine; `close` resta separato.

`continue` fallisce con conflitto se l'origine possiede già un successore. Il nuovo Progetto ha un
solo predecessore, appartiene all'Anno immediatamente successivo
(`destination_year = origin_year + 1`) e l'intera relazione è Tenant-bound; il server rifiuta
self-link, salti di Anno, ramificazioni e cicli prima di persistere.

Se una Spesa contrattuale è collegata a un Progetto, l'Anno del Progetto deve coincidere con l'Anno
della Data di Rinnovo/Scadenza. In caso contrario la generazione fallisce con preview esplicita e
propone di selezionare la Continuazione del Progetto nell'Anno corretto oppure lasciare la Spesa
senza Progetto; nessuna delle due autorità annuali prevale silenziosamente.

## Slice 6 — Contratti e Scadenziario

```text
GET  /api/v1/contracts?planning_year_id=&search=&filters[...]&page=
POST /api/v1/contracts
GET  /api/v1/contracts/{contract}
PUT  /api/v1/contracts/{contract}
POST /api/v1/contracts/{contract}/generation-preview
POST /api/v1/contracts/{contract}/synchronize
POST /api/v1/contracts/{contract}/cessation-preview
POST /api/v1/contracts/{contract}/cease
GET  /api/v1/contract-schedule?from=&to=&vendor_id=&cost_center_id=&status=
```

La generazione è idempotente per Source Key. Una Occorrenza contrattuale possiede una chiave base
stabile e le Righe usano chiavi distinte per ruolo `quote`/`actual`; il passaggio dell'Anno aggiunge
quindi un Effettivo senza sovrascrivere il Preventivo. Cancellare una Riga generata sopprime quella
Source Key specifica; cancellare l'intera Spesa sopprime tutte le Source Key generate già contenute.
La risposta Scadenziario contiene Data, Periodicità,
Importo, preavviso, stato (`to_generate`, `quote_generated`, `effective_generated`, `excluded`) e link
a Contratto, Termine, Spesa e Riga.

Per un Contratto Mensile, `synchronize` crea o aggiorna una sola Spesa per Anno e inserisce una Riga
per Scadenza. Per un Rinnovo Annuale crea una Riga interamente attribuita all'Anno della Data, anche
se il periodo di servizio attraversa l'anno successivo.

`cessation-preview` mostra occorrenze future rimosse dalla generazione, Effettivi già generati da
conservare e Righe future modificate manualmente che richiedono una scelta. `cease` richiede la Data
effettiva e una Nota quando produce una Rettifica; non applica pro-rata giornalieri.

## Slice 7 — Composizione Annuale e Avvio Storico

```text
GET  /api/v1/planning-years/{planningYear}/composition
POST /api/v1/planning-years/{planningYear}/composition/preview
POST /api/v1/planning-years/{planningYear}/composition/apply
POST /api/v1/planning-years/{planningYear}/activate-historical
```

La composizione presenta una lista di decisioni per elemento; non esegue riporti automatici opachi.
Ogni scelta è identificata e idempotente. Per gli Anni Storici, `activate-historical` abilita il
Previsto Ricostruito secondo la classificazione Extra corrente.

## Slice 8 — Cestino e Recupero

```text
GET    /api/v1/trash/expenses?planning_year_id=&deleted_from=&deleted_to=&search=&page=
GET    /api/v1/trash/expenses/{expense}
POST   /api/v1/trash/expenses/{expense}/restore-preview
POST   /api/v1/trash/expenses/{expense}/restore
```

Il contratto approvato riguarda soltanto le Spese e non accetta nomi di classe o tipi arbitrari dal
client. Restore Preview elenca dipendenze e impatto economico; il Restore non riattiva
ricorsivamente altre entità. Il delete di una Spesa generata
conserva nella risposta `generation_suppressed: true` o un equivalente contrattuale esplicito e il
job successivo rispetta la Source Key soppressa.

L'eliminazione definitiva delle Spese non appartiene al contratto approvato. Il purge terminale
degli Allegati già implementato è separato e non implica un endpoint generico di purge del Cestino.

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
GET /api/v1/report-series/options?planning_year_id=
GET /api/v1/reports/comparison?left[type]=&left[planning_year_id]=&right[type]=&right[planning_year_id]=&dimension=
GET /api/v1/reports/comparison/detail?...filtri contestuali...
GET /api/v1/dashboard?planning_year_id=
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

Il prodotto è Greenfield senza dati da preservare, come confermato esplicitamente dal proprietario
il 2026-08-12. Backend, Frontend e schema della singola Slice possono cambiare atomicamente e, al
completamento della Slice interessata, devono essere rimossi:

- payload e filtri `open/closed` della Spesa;
- `closure_outcome` sulla Spesa;
- `approved_current` e mutazioni `variation`;
- compatibilità con più Plafond dello stesso Tenant/Anno/Centro e ogni payload di Quote multiple;
- terminologia UI `Actual` e `Forecast`;
- Data contrattuale sintetica `01-01` usata al posto delle Scadenze reali.
