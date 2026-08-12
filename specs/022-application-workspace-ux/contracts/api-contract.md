# Contratto API di Programma

**Versione**: `PROPOSED TARGET` per `/api/v1`; endpoint non implementati
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
| `PLAFOND_INSUFFICIENT` | 422 | Capienza insufficiente; mantenere input e mostrare impatto |
| `BUDGET_STATE_CONFLICT` | 409 | Operazione incompatibile con Preparazione/Approvato/Chiuso |
| `BUDGET_DEPENDENCIES_EXIST` | 409 | Annullamento o Riapertura bloccati dagli eventi elencati |
| `YEAR_MISMATCH` | 422 | Data incompatibile con l'Anno; proporre il flusso assistito previsto |
| `RESTORE_DEPENDENCY_MISSING` | 409 | Prima ripristinare o sostituire le dipendenze indicate |
| `RESOURCE_NOT_FOUND` | 404 | Stesso codice e stessa envelope per ID inesistente o appartenente a un altro Tenant |
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

## Slice 2 — Plafond Singolo e Copertura Integrale

Le Righe accettano:

```json
{
  "funded_plafond_expense_id": 41
}
```

Il riferimento identifica l'unico Plafond dello stesso Tenant e Anno; la compatibilità tra Centri
di Costo resta `OPEN QUESTION` e la Slice 024 non può cambiare la regola corrente senza risposta.
La Riga è
interamente coperta oppure non coperta. Il server ricalcola i valori nella Base ufficiale e valida
la capienza prima di qualsiasi persistenza.

```text
POST /api/v1/expenses/{expense}/rows/{row}/coverage-preview
GET  /api/v1/plafonds?year=&cost_center_id=
POST /api/v1/plafonds/{plafond}/allocation-adjustments
POST /api/v1/plafonds/{plafond}/allocation-adjustments/preview
GET  /api/v1/plafonds/report?year=&cost_center_id=
```

La preview restituisce Allocazione, Disponibile, Importo richiesto, Copertura Prevista, Consumato e
Residuo. Una riduzione dell'allocazione restituisce anche le Righe che diverrebbero invalide.
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

`annul` richiede sempre `note`, conserva lo snapshot precedente e crea una Revisione. La risposta
Budget separa:

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

`reopening-preview` è read-only e restituisce `can_reopen`, la Chiusura corrente e le eventuali
Rettifiche bloccanti. `reopen` richiede sempre `note`, non elimina dati economici, rende non corrente
lo snapshot di Chiusura e crea una Revisione.

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
Progetto rimane un normale campo descrittivo e non sostituisce queste azioni. `continue` crea il
nuovo Progetto e il riferimento al precedente, ma non chiude l'origine; `close` resta separato.

`continue` fallisce con conflitto se l'origine possiede già un successore. Il nuovo Progetto ha un
solo predecessore, appartiene a un Anno successivo e l'intera relazione è Tenant-bound; il server
rifiuta self-link, ramificazioni e cicli prima di persistere.

Se una Spesa contrattuale è collegata a un Progetto, l'Anno del Progetto deve coincidere con l'Anno
della Data di Rinnovo/Scadenza. In caso contrario la generazione fallisce con preview esplicita e
propone di selezionare la Continuazione del Progetto nell'Anno corretto oppure lasciare la Spesa
senza Progetto; nessuna delle due autorità annuali prevale silenziosamente.

## Slice 6 — Contratti e Scadenziario

```text
GET  /api/v1/contracts?year=&search=&filters[...]&page=
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
GET    /api/v1/trash/expenses?year=&deleted_from=&deleted_to=&search=&page=
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

Il prodotto è Greenfield senza dati da preservare, come confermato esplicitamente dal proprietario
il 2026-08-12. Backend, Frontend e schema della singola Slice possono cambiare atomicamente e, al
completamento della Slice interessata, devono essere rimossi:

- payload e filtri `open/closed` della Spesa;
- `closure_outcome` sulla Spesa;
- `approved_current` e mutazioni `variation`;
- compatibilità con più Plafond dello stesso Tenant/Anno/Centro e ogni payload di Quote multiple;
- terminologia UI `Actual` e `Forecast`;
- Data contrattuale sintetica `01-01` usata al posto delle Scadenze reali.
