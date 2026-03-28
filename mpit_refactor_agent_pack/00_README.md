# MPIT Refactor Pack — Expense/Plafond Greenfield (Closed Decisions)

Questo pacchetto è il contesto operativo vincolante per l'agent che deve eseguire il refactor principale dell'app Frappe v16.

Non trattare il vecchio modello come compatibile.
Non reintrodurre logiche parallele.
Non lasciare decisioni implicite.

## File inclusi

- `01_AGENT_EXECUTION_PLAN.md` — piano operativo completo, regole di dominio chiuse, ordine di esecuzione, test e verifica finale.
- `02_FILE_LEVEL_DELETE_CREATE_MAP.md` — mappa precisa file/folder da eliminare, creare, riscrivere o archiviare.
- `03_UI_UX_RULES_V16.md` — regole UI/UX end-to-end, solo Frappe Desk nativo v16, nessuna complessità legacy nascosta.

## Decisioni di prodotto chiuse e non negoziabili

Queste decisioni sono già chiuse. In questa PR non vanno rimesse in discussione.

### Perimetro generale

- Refactor `hard cut` greenfield.
- I dati dev possono essere eliminati.
- Nessuna migrazione dati legacy da preservare.
- Nessuna compatibilità retroattiva applicativa col vecchio modello economico.
- Un solo motore numerico server-side per report, overview, chart, dashboard e riepiloghi.

### Dominio economico attivo

- `MPIT Budget` non resta come documento.
- `MPIT Budget Line`, `MPIT Planned Item`, `MPIT Actual Entry`, `MPIT Budget Addendum` non restano nel modello attivo.
- Le sole sorgenti economiche attive del prodotto sono:
  - `MPIT Contract`
  - `MPIT Expense`
- `MPIT Project` non è più un motore contabile autonomo.

### Contratti

- `MPIT Contract` resta economicamente attivo.
- Il forecast ufficiale include i contratti attivi che impattano l'anno.
- `MPIT Contract Term` resta attivo con funzione economica.
- Regola contratti chiusa:
  - se il contratto ha term attivi, il forecast del contratto deriva solo dai term che impattano l'anno;
  - se il contratto non ha term, si usa il fallback su `current_amount` + `billing_cycle` del contratto;
  - se il contratto ha term ma nessun term impatta l'anno, il forecast del contratto per quell'anno è `0`.
- Nessuna generazione persistita di righe budget o oggetti intermedi.

### Expense

- `MPIT Expense` è il documento economico fondante.
- `MPIT Expense` ha due nature documento:
  - `Ordinary`
  - `Plafond`
- `MPIT Expense` è annuale in modo rigido:
  - una expense appartiene a un solo `year`;
  - le righe operative di una expense ordinaria devono ricadere in quell'anno;
  - i casi reali multi-anno lato expense si rappresentano con più expense annuali.
- I casi multi-anno restano ammessi solo lato contratto, tramite allocazione del forecast del contratto sull'anno richiesto.

### Contesto e classificazione documento

- Una `Expense` ha un solo centro di costo.
- Una `Expense` ordinaria può essere standalone sul solo centro di costo.
- In v1, una `Expense` ordinaria può avere al massimo uno tra `project` e `contract`.
- In v1, `project` e `contract` insieme nello stesso documento non sono consentiti.
- Per `Ordinary`, la classificazione di funding è obbligatoria ed esclusiva:
  - `uses_plafond = 1` oppure `is_extra = 1`
  - mai entrambi
  - mai nessuno dei due
- Se `uses_plafond = 1`, `plafond_expense` è obbligatorio.
- Se `is_extra = 1`, `plafond_expense` deve essere vuoto.

### Plafond

- `Plafond` è un tipo di `MPIT Expense`, non un oggetto separato.
- Un solo documento `Plafond` non `Cancelled` per coppia `(year, cost_center)`.
- Le righe di un `Plafond` rappresentano solo:
  - dotazione iniziale
  - incremento
  - riduzione
  - rettifica
- Le righe di un `Plafond` non rappresentano consumo.
- Il consumo del plafond deriva solo dalle righe `Actual` attive delle `Expense` ordinarie che lo referenziano.
- Metrica ufficiale:
  - `plafond_total (documento) = somma righe attive del solo documento plafond`
  - `plafond_consumed (documento) = somma Actual attive delle ordinary expense che referenziano esattamente quel plafond_expense`
  - `plafond_remaining (documento) = plafond_total - plafond_consumed`
  - riepilogo cost center separato e solo aggregato
- Non esiste in v1 una metrica ufficiale separata di `forecast on plafond`.

### Righe expense

- Le righe di `Ordinary` possono essere:
  - `Estimate`
  - `Quote`
  - `Actual`
- Le righe hanno stato:
  - `Active`
  - `Replaced`
  - `Cancelled`
- `Estimate` e `Quote` sono forecast.
- `Actual` è speso reale.
- `Replaced` e `Cancelled` non entrano nei totali.
- La sostituzione non è automatica.
- In v1 la tracciabilità della sostituzione è minimale: si può usare un riferimento testuale alla riga sostituita, ma non esiste un workflow complesso di versioning.

### Totali e lessico

- Il forecast ufficiale è:
  - contratti attivi che impattano l'anno
  - righe `Estimate` attive
  - righe `Quote` attive
- `Closed` non esclude dai numeri.
- `Cancelled` esclude dai numeri.
- `total_active_net` non deve esistere nel modello finale.
- Il lessico legacy da rimuovere dalla UI e dal dominio attivo include almeno:
  - Budget
  - Budget Line
  - Snapshot
  - Addendum
  - Planned Item
  - Actual Entry
  - Coverage
  - Delta come documento separato
  - Cap
  - Budget Diff
  - Planned vs Exceptions

### Regole sui segni

- Su `Ordinary`, importi negativi consentiti solo su righe `Actual`, per storno / nota di credito / correzione.
- Su `Ordinary`, righe `Estimate` e `Quote` negative non consentite in v1.
- Su `Plafond`, importi negativi consentiti per riduzione/rettifica della dotazione.
- Il consumo del plafond non si deduce mai dal segno `-`.

## Documentazione locale da leggere prima di toccare il codice

- `AGENT_INSTRUCTIONS.md`
- `docs/how-to/01-apply-changes.md`
- `docs/reference/12-i18n.md`
- `docs/ux/field-help-text.md`
- `docs/reference/10-money-vat-annualization.md`

Nota: `docs/reference/10-money-vat-annualization.md` contiene parti legacy budget-centriche. Va letto solo per le regole sane di VAT/annualization e poi aggiornato o archiviato durante il refactor.

## Esito richiesto dalla PR

La PR è accettabile solo se il prodotto finale:

- parte senza doctypes legacy nel dominio attivo;
- mostra solo naming coerente col nuovo modello;
- calcola tutti i numeri economici da un solo motore server-side;
- non ha hook, report, chart, workspace, test o documentazione attiva che dipendono ancora dal budget engine legacy.
