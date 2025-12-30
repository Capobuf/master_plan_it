# Reference: Money Rules (VAT + Normalizzazione + Annualizzazione) — MPIT (Frappe v15)

Fonte di verità per calcoli, report e stampa.

---

## 1) Strict VAT mode (CHIUSO)

Se `amount != 0`:
- `vat_rate` è obbligatorio (0 valido)
- se manca sulla riga e manca anche in `MPIT User Preferences.default_vat_rate` → blocco salvataggio

Se `amount == 0`:
- `vat_rate` può essere vuoto (ma consigliato 0).

Nessun default IVA tenant: i default sono solo per-utente.

---

## 2) Normalizzazione net/vat/gross

Sia:
- `r = vat_rate / 100`

Caso A: input netto (`includes_vat = 0`)
- net = amount
- gross = net * (1 + r)
- vat = gross - net

Caso B: input lordo (`includes_vat = 1`)
- gross = amount
- net = gross / (1 + r)
- vat = gross - net

Arrotondamento:
- `frappe.utils.flt(value, 2)` (salvo diversa precisione decisa)

### Totali Budget (form MPIT Budget)
- Il DocType MPIT Budget espone i campi `total_amount_input`, `total_amount_net`, `total_amount_vat`, `total_amount_gross`.
- I valori sono calcolati nel controller server-side sommando le righe della tabella `lines` (MPIT Budget Line), senza JavaScript.
- Ogni somma usa `frappe.utils.flt(..., 2)` per garantire arrotondamento a 2 decimali coerente con split net/vat/gross.

---

## 3) Annualizzazione (CHIUSO)

Recurrence: `Monthly, Quarterly, Annual, None`

Overlap:
- calcolare mesi di overlap tra anno del Budget.year (MPIT Year start/end) e range riga (mesi toccati, non solo completi)
- se overlap = 0 → blocco salvataggio (regola A)

Regole:
- Monthly: annual = amount * overlap_months
- Quarterly: annual = amount * (overlap_months / 3)
- Annual: annual = amount * (overlap_months / 12)
- None: one-off → annual = amount (se overlap > 0)

Applicazione:
- normalizzare net/vat/gross prima
- annualizzare net/vat/gross separatamente

---

## 4) Backfill (anti-regressione)

Per dati storici senza VAT:
- set `vat_rate=0`, `includes_vat=0`
- net/gross=amount, vat=0
Non si “inventa” IVA storica.
---

## 5) Dual-Mode Controller (Phase 6 Implementation) ✅

**Problema:** Transizione da campo legacy `amount` a nuovo triple `amount_net/vat/gross`.

**Soluzione:** Controller intelligente che supporta entrambi i flussi.

### 5.1 Budget Line VAT Calculation (_compute_vat_split)

```python
def _compute_vat_split(self):
    """Compute amount_net/_vat/_gross from input fields using user defaults."""
    default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)
    default_includes = mpit_user_prefs.get_default_includes_vat(frappe.session.user)
    
    for line in self.lines:
        # Determine source amount: prefer amount_net (new field), fallback to amount (legacy)
        if line.amount_net:
            # NEW FLOW: amount_net is source of truth
            # Apply VAT defaults if not specified
            if line.vat_rate is None and default_vat is not None:
                line.vat_rate = default_vat
            
            # Compute VAT and gross from net
            if line.vat_rate:
                vat_rate_decimal = line.vat_rate / 100.0
                line.amount_vat = line.amount_net * vat_rate_decimal
                line.amount_gross = line.amount_net + line.amount_vat
            else:
                line.amount_vat = 0.0
                line.amount_gross = line.amount_net
                
        elif line.amount:
            # LEGACY FLOW: amount is source, split based on includes_vat flag
            # Apply defaults if field is empty
            if line.vat_rate is None and default_vat is not None:
                line.vat_rate = default_vat
            if not line.amount_includes_vat and default_includes:
                line.amount_includes_vat = 1
            
            # Strict VAT validation
            final_vat_rate = tax.validate_strict_vat(
                line.amount,
                line.vat_rate,
                default_vat,
                field_label=f"Line {line.idx} Amount"
            )
            
            # Compute split
            net, vat, gross = tax.split_net_vat_gross(
                line.amount,
                final_vat_rate,
                bool(line.amount_includes_vat)
            )
            
            line.amount_net = net
            line.amount_vat = vat
            line.amount_gross = gross
```

### 5.2 Campo Legacy Hidden

In exported DocTypes (Budget Line, Actual Entry, Project Quote):
```json
{
  "fieldname": "amount",
  "fieldtype": "Currency",
  "label": "Amount",
  "read_only": 1,
  "hidden": 1
}
```

**Risultato:**
- UI mostra solo `amount_net/vat/gross` (campi moderni)
- Controller accetta entrambi i flussi
- Dati legacy continuano a funzionare (backfill già applicato)
- Nuovo codice usa `amount_net` direttamente

### 5.3 Annualization Source Fix

`_compute_lines_annualization()` usa `line.amount_net` dopo VAT split:
```python
# Calculate annualized amounts
annual_net = annualization.annualize(
    line.amount_net,  # ← Sempre popolato dal dual-mode controller
    line.recurrence_rule or "None",
    overlap_months_count
)
```

**CRITICAL:** VAT split (`_compute_vat_split()`) DEVE essere chiamato PRIMA di annualization (`_compute_lines_annualization()`) nella sequenza `validate()`.

### 5.4 Report Anti-Regression Pattern

Per compatibilità con dati storici, i report usano COALESCE:
```sql
SELECT
    COALESCE(annual_net, amount) AS budget_net,
    COALESCE(amount_net, amount) AS actual_net
FROM ...
```

Questo garantisce:
- Record nuovi: usano `annual_net`/`amount_net`
- Record legacy (pre-Phase 4): usano `amount` come fallback
