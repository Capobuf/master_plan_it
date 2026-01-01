# MPIT Budget Engine v3 — Decisioni, flusso e modelli (doc-as-code)

Data: 2026-01-01  
Scope: revisione completa del Budget (live + governance), semplificazione Contratti/Progetti, eliminazione legacy e riduzione debito tecnico.

---

## 0. Obiettivo

Rendere il **Budget**:

- **Affidabile**: numeri coerenti e deterministici, senza ambiguità tra documenti.
- **Live**: si aggiorna automaticamente quando cambiano le sorgenti **validate**.
- **Governato**: esiste una versione **Approved** (immutabile) per limiti e confronto, con gestione incrementi in corso d’anno.
- **Semplice**: pochi concetti, nessun ramo logico “speciale”, niente implementazioni appese.

---

## 1. Modelli “ufficiali” a cui ci allineiamo (per non reinventare la ruota)

Questo design si allinea a un modello FP&A comune:

1) **Rolling Forecast (Live Plan)**  
   Piano operativo aggiornato continuamente, con orizzonte limitato (rolling horizon).

2) **Approved Budget (Snapshot)**  
   Fotografia immutabile concordata con il cliente, usata per limiti e confronti.

3) **Budget Adjustments / Addendum (Revisions)**  
   Variazioni approvate durante l’anno che modificano il “cap” (limite) senza riscrivere la storia.

**Nota di nomenclatura:**  
“Baseline” come *secondo budget parallelo* viene eliminata. La **funzione** baseline è coperta da **Snapshot Approved + Addendum**.

---

## 2. Principi guida

1) **Bozze e proposte non sporcano il piano**  
   Draft/Proposed non impattano budget e non attivano refresh.

2) **Date-driven**  
   Attribuzione per anno/mese basata su date/periodi (inizio/fine), non su “anno chiuso”.

3) **Rolling horizon**  
   Aggiornare solo **anno corrente + 1** per evitare aggiornamenti su anni remoti.

4) **Separare operatività e governance**  
   Live cambia; Approved resta consultabile; Addendum gestisce ampliamenti senza caos.

5) **Semplificazione radicale**  
   Un solo modello per concetto (es. Contratti = Termini). Niente casi speciali.

---

## 3. Glossario (termini funzionali)

- **Budget Live / Rolling Forecast**: piano operativo auto-aggiornato (orizzonte anno corrente + 1).
- **Snapshot Approved**: copia immutabile del budget concordato col cliente.
- **Addendum Approved**: delta approvato durante l’anno (per Cost Center) che aumenta/diminuisce il limite.
- **Cap (limite ufficiale)**: Snapshot Approved + Somma Addendum Approved.
- **Sorgenti**: documenti che generano righe budget (Contratti, Progetti/Planned Items, Spese/Actual).
- **Rolling horizon**: aggiorniamo solo anno corrente e anno successivo.

---

## 4. Decisioni principali (con motivazione)

### 4.1 Eliminare “Baseline” come documento parallelo
**Decisione:** nessun budget “baseline/forecast” duplicato.  
**Motivo:** genera drift e ambiguità.  
**Sostituto:** Snapshot Approved + Addendum.

### 4.2 Budget Live aggiornato automaticamente + pulsante “Forza refresh”
**Decisione:** refresh automatico su eventi (solo per sorgenti validate) + fallback periodico; bottone manuale resta.  
**Motivo:** riduce lavoro manuale e mantiene coerenza.

### 4.3 Draft/Proposed non impattano budget (né trigger refresh)
**Decisione:** bozze/proposte sono escluse dal budget e non attivano refresh.  
**Motivo:** impedire che ipotesi e tentativi contaminino il piano operativo.

### 4.4 Righe generate: cancellare, non disattivare
**Decisione:** righe generate non più valide vengono **cancellate** (delete), non `is_active=0`.  
**Motivo:** evita “spazzatura” e incoerenze tra totali e report.

> Audit/Storico: lo storico del “live” non viene preservato tramite righe disattivate; si usa **Snapshot** (e, se serve, la timeline/versioning nativa per tracciare modifiche sui documenti sorgente).

### 4.5 Contratti: Strada A — Termini Contratto (unico modello)
**Decisione:** un contratto possiede una tabella **Termini**: `from_date`, `to_date` (opzionale), `amount_net`, `billing_cycle` (e campi necessari).  
**Motivo:** copre cambi prezzo/rinnovi/stop senza logiche speciali (no `spread_months`, no rate schedule separato).

### 4.6 Progetti: “Planned Items” come unità unica e flessibile
**Decisione:** il progetto gestisce **voci pianificate** come elementi datati e collegabili (quote/contratti/actual).  
**Motivo:** serve flessibilità reale: acquisto una-tantum, software che diventa ricorrente, modifica contratto esistente, ecc.

**Scelta implementativa (minimo stravolgimento):**  
Planned Items come **child table del Project** (coerente con l’attuale struttura a child table: Quote/Allocation/Milestone).  
Motivo: UX semplice (tutto nel progetto), minori migrazioni, meno nuovi list view e permessi.

### 4.7 Distribuzione progetto: solo quando multi-anno, solo 3 opzioni
**Decisione:** la scelta distribuzione compare solo se una voce attraversa più anni:
- Uniforme
- Tutto all’inizio
- Tutto alla fine  
**Motivo:** semplicità e leggibilità, evitare modalità “custom per mese”.

### 4.8 Orizzonte limitato per auto-renew
**Decisione:** refresh/popola solo anno corrente + 1.  
**Motivo:** evita generazione infinita e costi di refresh su anni remoti.

### 4.9 Budget “anno chiuso”: auto-refresh disabilitato + modalità archiviata (utente non edita comunque il Live)
**Decisione:** dopo la `end_date` dell’anno, il budget di quell’anno entra in modalità “anno chiuso”: auto-refresh **OFF** e comunicazione esplicita all’utente. Il Budget Live è **sempre** non-editabile dall’utente; questa modalità aggiunge solo una protezione operativa e di performance.  
**Motivo:** performance e coerenza storica.  
**Eccezione:** rimane il pulsante manuale con warning.

**Nota:** con rolling horizon gli anni passati non vengono normalmente aggiornati; lo “switch off” post anno è una safety net e una protezione ulteriore.

---

## 5. Nomenclatura e “tipi” di Budget

### 5.1 Budget Live
- È il documento operativo “LIVE”.
- **Non è modificabile direttamente** (righe gestite dal sistema): si modifica solo tramite **sorgenti** (Contratti, Planned Items, Actual) e tramite Addendum per limiti.

### 5.2 Snapshot Approved (definizione confermata)
**Decisione:** lo Snapshot è una **copia del Budget Live** (stesso DocType) con stato **Approved**, immutabile e non auto-refreshato.  
Serve come riferimento ufficiale col cliente.

**Invariante anti-confusione:** lo stato **Approved** è consentito solo per documenti “APP” (es. naming `-APP-` o flag equivalente). L’unico flusso supportato per creare un budget Approved è l’azione “Crea Snapshot Approved”.

### 5.3 Naming coerente con le Preferenze Utente
Manteniamo prefix e digits dalle preferenze, distinguendo per token:

- Live: `{prefix}{year}-LIVE-{NN}`
- Snapshot: `{prefix}{year}-APP-{NN}`

### 5.4 Chiusura anno configurabile (fonte di verità)
La data di chiusura è l’`end_date` di **MPIT Year** (o fallback calendario).  
Le preferenze utente possono solo aiutare la creazione/gestione dei Year, ma non cambiano la verità del periodo.

---

## 6. Flussi: Contratti, Progetti, Spese (effetti osservabili)

## 6.1 Contratti (Termini)
- Un contratto **validato** genera righe nel budget per i mesi/anni intersecati dai Termini.
- Un cambio prezzo = nuovo Termine con nuova `from_date`.

**Auto-renew:** genera impatto solo fino a **anno corrente + 1**.

## 6.2 Progetti: lifecycle e Planned Items

### 6.2.1 Stato iniziale: voce generica
**Effetto:** quando creo un progetto, esiste una voce generica (stimata) come Planned Item iniziale.

### 6.2.2 Approvazione cliente: dettaglio con quotazioni
**Effetto:**
- dettaglio il progetto creando più Planned Items e collegando (opzionalmente) Quote.
- ri-approvo il progetto.

### 6.2.3 Esecuzione: collaudo e pagamento
**Effetto:**
- una voce viene collaudata → si conferma `spend_date`
- il sistema può generare una spesa/Actual in bozza da confermare (poi Verified).

### 6.2.4 Planned Item collegato a Contratto o Actual: opzione B (scelta confermata)
**Decisione (B):** l’item resta visibile ma non deve generare doppio conteggio.

**Effetto:**
- se un Planned Item crea/collega un Contratto (ricorrente) oppure genera un’Actual (spesa), l’item resta come riferimento ma viene marcato come **“coperto”** e **escluso** dal calcolo budget.

---

## 7. Inclusione nel budget e trigger refresh (regole)

### 7.1 Stati sorgenti: canonico MPIT (dalla codebase attuale)

**MPIT Contract — status options (attuale):**
- Draft
- Active
- Pending Renewal
- Renewed
- Cancelled
- Expired

**Regola v3 (inclusione):**
- Inclusi: `Active`, `Pending Renewal`, `Renewed` (validati)
- Esclusi: `Draft`, `Cancelled`, `Expired`

**MPIT Project — status options (attuale):**
- Draft
- Proposed
- Approved
- In Progress
- On Hold
- Completed
- Cancelled

**Regola v3 (inclusione):**
- Inclusi: `Approved`, `In Progress`, `On Hold`.
- `Completed`: incluso **solo se** ha Planned Items non “coperti” oppure `spend_date` rilevanti nel periodo (altrimenti non impatta il budget).
- Esclusi: `Draft`, `Proposed`, `Cancelled`

### 7.2 Matrice effetti (inclusione + trigger)
Per ogni sorgente:

- **Draft / Proposed**
  - Inclusione nel budget: NO
  - Trigger auto-refresh: NO (nessun refresh su edit di bozze)
  - Trigger su transizione: SÌ quando si passa da/verso uno stato validato (per entrare/uscire dal budget)

- **Validati** (vedi regole sopra)
  - Inclusione nel budget: SÌ (date-driven)
  - Trigger auto-refresh: SÌ (entro rolling horizon)

- **Finali esclusi** (es. Cancelled/Expired)
  - Inclusione nel budget: NO
  - Trigger auto-refresh: SÌ se l’oggetto era prima incluso (per rimuoverlo dal budget)

### 7.3 Edge case stati (regressione e ripristino)

**Validato → Draft/Proposed**  
- esce dal budget
- righe generate cancellate
- refresh anno corrente + 1

**Draft/Proposed → Validato**  
- entra nel budget
- righe generate create
- refresh anno corrente + 1

**Modifiche su Draft/Proposed**  
- nessun refresh

---

## 8. Multi-anno e distribuzione (algoritmi definitivi)

### 8.1 Regola di intersezione
Un elemento impatta un anno se il suo periodo interseca il periodo dell’anno.

### 8.2 Distribuzione (solo multi-anno, 3 modalità)

- **Uniforme**: ripartizione uniforme sui **mesi intersecati** dal periodo (stesso importo per ogni mese “toccato”; rounding gestito sull’ultimo mese).
- **Tutto all’inizio**: tutto nel mese di `start_date`.
- **Tutto alla fine**: tutto nel mese di `end_date`.

### 8.3 Caso “pagamento il 10”
Non esiste distribuzione custom. Si ottiene precisione spezzando in Planned Items con periodi che cadono nel mese desiderato e usando `spend_date`.

**Regole di precedenza (deterministiche):**
- Se `spend_date` è presente → l’importo è attribuito al **mese/anno della spend_date** (la distribuzione è ignorata).
- Se `spend_date` è assente:
  - se multi-anno → si applica la distribuzione scelta (Uniforme / Inizio / Fine)
  - se single-year → default implicito: **Uniforme** sui mesi intersecati (la UI non mostra opzioni perché non necessarie).

---

## 9. Budget Live: aggiornamento automatico e limiti di performance

### 9.1 Trigger live (eventi)
- Salvando/aggiornando una sorgente **validata**, il budget si riallinea automaticamente per:
  - anno corrente
  - anno successivo

### 9.2 Fallback periodico
Un job periodico riallinea (per sicurezza e robustezza).

### 9.3 Anno chiuso: auto-refresh OFF + warning su refresh manuale
Dopo `MPIT Year.end_date`:
- quel Budget Live è marcato come **anno chiuso** (auto-refresh OFF) e mostra warning; l’utente comunque non edita direttamente il Live
- auto-refresh per quell’anno diventa **OFF**
- resta solo “Forza refresh” con warning: “anno chiuso: refresh manuale può modificare lo storico”.

> Read-only qui significa: blocco editing utente. Le operazioni di sistema (refresh manuale) restano consentite.

---

## 10. Governance con il cliente: Snapshot Approved + Addendum

### 10.1 Snapshot Approved
**Effetto:**
- si crea una copia “APP” del budget live concordato.
- è immutabile e consultabile sempre.

### 10.2 Addendum Approved (incrementi in corso d’anno)
**Effetto:**
- un accordo che aumenta **o riduce** un Cost Center viene registrato come Addendum (delta positivo o negativo).
- il limite ufficiale (Cap) diventa: Snapshot + Addendum.

**Dimensione del limite:** il cap è **primariamente per Cost Center**. Eventuali drilldown per Project sono diagnostici (non “cap” per project).

### 10.3 Report e controllo limiti
Report mostrano per Cost Center:
- Snapshot Approved (base)
- Addendum Approved (delta)
- Cap (limite)
- Actual
- Over/Remaining

---

## 11. Invarianti (coerenza dati)

1) Tutto ciò che conta per limiti/actual deve avere **Cost Center** (diretto o derivabile).  
2) Le righe generate devono essere sempre **rigenerabili** (idempotenza).  
3) Nessun doppio conteggio tra Planned Items / Contratti derivati / Actual generate  
   (Planned Item “coperto” = escluso dal calcolo).  
4) Il Budget Live non è una superficie di editing: si modifica tramite sorgenti.

---

## 12. Pulizia legacy (no debito tecnico)

Da rimuovere completamente:
- baseline (tipo/branch logico)
- `is_active` per righe generate e filtri correlati nei report
- coercizioni workflow/docstatus sul Budget
- spread months e logiche correlate
- rate schedule separato (sostituito dai Termini)

Obiettivo: nessuna logica morta o campi lasciati “a metà”.

---

## 13. Criteri di accettazione (test funzionali)

1) Bozze non impattano (no inclusione, no refresh).  
2) Regressione stato rimuove dal budget cancellando righe generate.  
3) Multi-anno: impatta entrambi gli anni, distribuzione visibile solo se multi-anno.  
4) Orizzonte: refresh solo anno corrente + 1.  
5) Fine anno: budget read-only, auto-refresh off, manual refresh con warning.  
6) Snapshot: immutabile e confrontabile.  
7) Addendum: aumenta cap e report aggiornati.  
8) Planned Items: collegamento a contratti/actual non genera doppio conteggio (item resta visibile ma escluso).  
9) Budget Live non è editabile direttamente: modifiche solo via sorgenti.

---

## 14. Stato del documento

Questo documento è “convalidato” e costituisce la base per l’implementazione tecnica (doc-as-code).
