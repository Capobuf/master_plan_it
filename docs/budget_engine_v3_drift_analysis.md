# Budget Engine V3 — Drift Analysis Finale

**Data**: 2026-01-05  
**Revisione**: Approfondimento UI/UX, Lifecycle Progetti, Contract Terms

---

## 1. Sommario — Gap Confermati

Il product owner ha confermato che tutti i gap identificati sono reali e vanno risolti:

| # | Gap | Priorità | Stato |
|---|-----|:--------:|:-----:|
| 1 | Contract Terms child table | **ALTA** | ✅ COMPLETATO |
| 2 | Planned Item VAT fields | **ALTA** | ✅ COMPLETATO |
| 3 | Project → Planned Items inline | **MEDIA** | Da implementare |
| 4 | Project totals da Planned Items | **MEDIA** | ✅ COMPLETATO |
| 5 | Project lifecycle estimation → quotes | **ALTA** | Da progettare |
| 6 | Amount editable on submitted Planned Item | **MEDIA** | ✅ COMPLETATO |

---

## 2. Contract Terms — Stato Attuale

### 2.1 Decisione Originale (§4.5, §6.1)

> "Un contratto possiede una tabella **Termini**: `from_date`, `to_date` (opzionale), `amount_net`, `billing_cycle`"
> 
> "Un cambio prezzo = nuovo Termine con nuova `from_date`"

### 2.2 Stato Attuale — IMPLEMENTATO ✅

**DocType**: `MPIT Contract Term` (child table) creato 2026-01-05

**Schema implementato**:
- `from_date` (Date, reqd) - Inizio validità termine
- `to_date` (Date) - Fine validità (auto-calcolato se vuoto: +1 anno -1 giorno, oppure giorno prima del prossimo termine)
- `amount` (Currency, reqd) - Importo per ciclo billing
- `amount_includes_vat` (Check) - Flag IVA inclusa
- `vat_rate` (Percent) - Aliquota IVA
- `billing_cycle` (Select) - Monthly/Quarterly/Annual/Other
- Campi computed: `amount_net`, `amount_vat`, `amount_gross`, `monthly_amount_net`
- `notes` (Small Text) - Motivo cambio prezzo
- `attachment` (Attach) - Documento variazione

**Budget Engine**: `_generate_contract_term_lines()` in mpit_budget.py L232-275
- Itera su tutti i termini del contratto
- Calcola overlap con anno fiscale per ogni termine
- Genera `source_key = "CONTRACT::{contract.name}::TERM::{term.name}"`

**Auto-calcolo to_date**: `_auto_compute_term_end_dates()` in mpit_contract.py
- Termini non ultimi: `to_date = next.from_date - 1 giorno`
- Ultimo termine: `to_date = from_date + 1 anno - 1 giorno` (se vuoto)
- Client-side JS con `frappe.confirm()` per sovrascrittura manuale

---

## 3. Planned Item — Schema VAT ✅ COMPLETATO

### 3.1 Decisione Originale

Non specificato esplicitamente, ma coerenza con resto del sistema richiede gestione VAT.

### 3.2 Stato Attuale — IMPLEMENTATO ✅

**Campi VAT implementati in `mpit_planned_item.json`:**

| Campo | Tipo | Note |
|-------|------|------|
| `amount_includes_vat` | Check, default=0 | "IVA Inclusa?" |
| `vat_rate` | Percent | Default da MPIT Settings |
| `amount_net` | Currency, read_only | Calcolato |
| `amount_vat` | Currency, read_only | Calcolato |
| `amount_gross` | Currency, read_only | Calcolato |

**Caratteristiche:**
- Logica VAT condivisa con Contract/Actual Entry via `master_plan_it.tax`
- Defaults applicati via `mpit_planned_item.js` con `apply_defaults_for_planned_item()`
- Ricalcolo automatico in `_compute_vat_amounts()`

### 3.3 Amount Editable su Documenti Submitted ✅

I campi `amount`, `amount_includes_vat`, `vat_rate` sono ora editabili anche dopo il submit:
- `allow_on_submit: 1` nel JSON per tutti i campi amount-related
- Hook `before_update_after_submit` ricalcola automaticamente i campi VAT
- Hook `on_update_after_submit` aggiorna i totali del progetto
- Audit trail via Version log (`track_changes: 1`)
- Campi immutabili: `project`, `description`, `dates`, `distribution`, `item_type`, `vendor`

---

## 4. Progetto Lifecycle — Estimation → Quotes → Planned Items

### 4.1 Flusso Documentato (§6.2)

```
6.2.1 Stato iniziale: voce generica
      → quando creo un progetto, esiste una voce generica (stimata) come Planned Item iniziale.

6.2.2 Approvazione cliente: dettaglio con quotazioni
      → dettaglio il progetto creando più Planned Items e collegando (opzionalmente) Quote.
      → ri-approvo il progetto.

6.2.3 Esecuzione: collaudo e pagamento
      → una voce viene collaudata → si conferma spend_date
      → il sistema può generare una spesa/Actual in bozza da confermare.
```

### 4.2 Flusso Reale Utente (Confermato dal PO)

```
1. STIMA INIZIALE
   → Creo progetto con voci "fittizie" tipo:
     - "Elettricista" €1000
     - "Materiale" €500
   → Scopo: presentare idea e costo supposto agli stakeholder

2. APPROVAZIONE STAKEHOLDER
   → Stakeholder dicono OK

3. RICHIESTA PREVENTIVI
   → Chiedo preventivi reali ai fornitori
   → Ricevo: Ditta X €950, Materiale €480

4. DETTAGLIO PLANNED ITEMS
   → Aggiorno/sostituisco le voci con importi reali
   → Collego (opzionalmente) a Quote/Contratti

5. ESECUZIONE
   → Opere completate
   → Creo Actual Entry per registrare spesa effettiva
   → Planned Item marcato "coperto"
```

### 4.3 Cosa Manca per Supportare Questo Flusso

| Componente | Stato | Note |
|------------|:-----:|------|
| Planned Item standalone | ✅ | Esiste |
| Planned Item inline da Project | ❌ | Solo sidebar link |
| Quote DocType | ❌ | Rimosso in v3 |
| "Stima" vs "Preventivo" | ❌ | Non differenziati |
| Transizione Draft → Submitted | ✅ | Via docstatus |
| Project totals da Planned Items | ❌ | TODO commentato |

### 4.4 Domande Aperte per Design UX

1. **Stima vs Preventivo**: Serve un campo `item_type` = "Estimate" | "Quote" | "Actual"?
   - Estimate: voce iniziale approssimativa
   - Quote: preventivo ricevuto (con riferimento fornitore?)
   - Actual: costo reale (→ link a Actual Entry)

2. **Quote DocType**: Reintrodurre un DocType `MPIT Quote` separato, o basta un campo `vendor` + `quote_ref` nel Planned Item?

3. **Visualizzazione inline**:
   - Tabella read-only che mostra Planned Items del progetto?
   - Pulsante "Aggiungi voce" che apre Quick Entry?
   - Editing inline (più complesso con docstatus)?

4. **Project Status e Planned Items**:
   - Status "Draft": nessun Planned Item reqd
   - Status "Proposed": almeno 1 Planned Item (stima)?
   - Status "Approved": almeno 1 Planned Item submitted?

---

## 5. Project → Planned Items Inline

### 5.1 Opzione Scelta: (A) Sezione Inline

Il PO ha scelto l'opzione A (query table inline).

### 5.2 Implementazione Proposta

**Aggiunta in `mpit_project.json`:**
```json
{
    "fieldname": "planned_items_section",
    "fieldtype": "Section Break",
    "label": "Planned Items"
},
{
    "fieldname": "planned_items_html",
    "fieldtype": "HTML",
    "label": "Planned Items Table"
}
```

**Logica in `mpit_project.js`:**
```javascript
async refresh(frm) {
    if (!frm.is_new()) {
        await render_planned_items_table(frm);
    }
}

async function render_planned_items_table(frm) {
    const items = await frappe.call({
        method: "frappe.client.get_list",
        args: {
            doctype: "MPIT Planned Item",
            filters: { project: frm.doc.name },
            fields: ["name", "description", "amount", "spend_date", "is_covered", "docstatus"]
        }
    });
    // Render HTML table with Add button
}
```

---

## 6. Project Totals

### 6.1 Stato Attuale

**File: `mpit_project.py` L45-70**
```python
def _compute_project_totals(self) -> None:
    # v3: Allocations and Quotes are removed. 
    # Totals are pending re-implementation based on Planned Items.
    planned_total = 0.0  # ← HARDCODED
    quoted_total = 0.0   # ← HARDCODED
```

### 6.2 Implementazione Proposta

```python
def _compute_project_totals(self) -> None:
    planned_items = frappe.get_all(
        "MPIT Planned Item",
        filters={"project": self.name, "docstatus": ["!=", 2]},  # Include Draft + Submitted
        fields=["amount", "amount_net", "is_covered"]
    )
    
    # Use amount_net if available, else amount (backward compat)
    planned_total = sum(
        flt(item.amount_net or item.amount or 0) 
        for item in planned_items 
        if not item.is_covered
    )
    
    # quoted_total: items with item_type = "Quote"? TBD
    quoted_total = 0.0  # Depends on design decision
    
    self.planned_total_net = flt(planned_total, 2)
    self.quoted_total_net = flt(quoted_total, 2)
    self.expected_total_net = flt(max(planned_total, quoted_total) + verified_deltas, 2)
```

---

## 7. Riepilogo Implementazione

### 7.1 Fase 1: Schema (Priorità Alta)

1. ~~**Creare DocType `MPIT Contract Term`** (child table)~~ ✅ COMPLETATO
2. ~~**Aggiungere campi VAT** a `MPIT Planned Item`~~ ✅ COMPLETATO
3. ~~**Creare `mpit_planned_item.js`**~~ ✅ COMPLETATO
4. **Fix description obsoleta** in `mpit_project.json`

### 7.2 Fase 2: Budget Engine (Priorità Alta)

5. ~~**Refactor `_generate_contract_lines`** per usare Terms~~ ✅ COMPLETATO
6. ~~**Aggiornare `_generate_planned_item_lines`** per usare `amount_net`~~ ✅ COMPLETATO

### 7.3 Fase 3: UX Progetto (Priorità Media)

7. **Aggiungere sezione inline** Planned Items nel form Progetto
8. ~~**Implementare `_compute_project_totals`** da Planned Items~~ ✅ COMPLETATO
9. **Decidere design** per Stima/Preventivo/Actual

### 7.4 Fase 4: Migrazione (Se dati esistenti)

10. **Migrare contratti** esistenti: creare un Term iniziale con `current_amount`
11. **Backfill `amount_net`** per Planned Items esistenti

---

## 8. Allegato: Riferimenti Documentazione

| Sezione Doc | Contenuto | Stato Implementazione |
|-------------|-----------|:---------------------:|
| §4.5 Contract Termini | Child table per cambi prezzo | ✅ |
| §6.1 Contratti | Un cambio prezzo = nuovo Termine | ✅ |
| §6.2.1 Stato iniziale | Voce generica stimata | ⚠️ Parziale |
| §6.2.2 Dettaglio Quote | Collegare Quote a Planned Items | ❌ Quote rimosso |
| §6.2.3 Esecuzione | Conferma spend_date, genera Actual | ✅ |
| Q&A §10.2 | Planned Item standalone con coverage | ✅ |
| Q&A §8 | Quote non alimentano budget | ✅ (rimosso) |
