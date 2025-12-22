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

---

## 3) Annualizzazione (CHIUSO)

Recurrence: `Monthly, Quarterly, Annual, Custom, None`

Overlap:
- calcolare mesi di overlap tra anno del Budget.year (MPIT Year start/end) e range riga
- se overlap = 0 → blocco salvataggio (regola A)

Regole:
- Monthly: annual = amount * overlap_months
- Quarterly: annual = amount * (overlap_months / 3)
- Annual: annual = amount * (overlap_months / 12)
- None: one-off → annual = amount (se overlap > 0)
- Custom (Option A): richiede `custom_period_months` > 0
  - annual = amount * (overlap_months / custom_period_months)

Applicazione:
- normalizzare net/vat/gross prima
- annualizzare net/vat/gross separatamente

---

## 4) Backfill (anti-regressione)

Per dati storici senza VAT:
- set `vat_rate=0`, `includes_vat=0`
- net/gross=amount, vat=0
Non si “inventa” IVA storica.
