# Baseline Import Template (V1)

Obiettivo: preparare un file CSV “client-ready” per inserire le spese baseline in MPIT senza logica custom.

## Come usare il template
1. Scarica `templates/baseline_template.csv`.
2. Compila le colonne rispettando i formati indicati sotto.
3. Valida rapidamente in `bench console` (vedi sezione “Preflight rapido”) prima di inserire i dati in UI.

## Colonne e regole
- `year` (obbligatorio): nome dell’MPIT Year (es. `2025`).
- `category` (obbligatorio): nome categoria esistente.
- `vendor` (facoltativo): nome vendor; lascia vuoto se non applicabile.
- `description` (obbligatorio): testo breve della spesa.
- `recurrence_rule` (obbligatorio): `Monthly` | `Quarterly` | `Annual` | `Custom` | `None`.
- `custom_period_months` (richiesto solo se `recurrence_rule=Custom`): intero 1-12.
- `amount` (obbligatorio): importo della spesa di partenza (net o gross secondo colonna successiva).
- `amount_includes_vat` (obbligatorio): `1` se l’importo include IVA, altrimenti `0`.
- `vat_rate` (obbligatorio): percentuale IVA (es. `22`), usa `0` per esenzione.
- `period_start_date` / `period_end_date` (facoltativi): date ISO `YYYY-MM-DD`; servono per calcolare l’overlap con l’anno MPIT (Rule A blocca overlap zero).

## Esempi validi
- Mensile standard: anno 2025, Connectivity, TIM, 100 €/mese, IVA 22%, includes=1, recurrence_rule=Monthly.
- Annuale senza IVA: anno 2025, Insurance, vendor vuoto, 1200 €, includes=0, vat_rate=0, recurrence_rule=Annual.
- Custom 6 mesi: recurrence_rule=Custom, custom_period_months=6, period_start/end coerenti con l’anno.

## Preflight rapido (opzionale)
In `bench console`, puoi caricare il CSV e controllare i campi obbligatori/numerici:
```python
import csv, frappe
path = "/path/al/file/baseline_template.csv"
errors = []
with open(path) as f:
    reader = csv.DictReader(f)
    for i, row in enumerate(reader, start=2):  # header = riga 1
        if not row["year"]:
            errors.append((i, "year mancante"))
        if not row["category"]:
            errors.append((i, "category mancante"))
        if row.get("recurrence_rule") == "Custom" and not row.get("custom_period_months"):
            errors.append((i, "custom_period_months richiesto per Custom"))
        try:
            float(row["amount"])
            float(row["vat_rate"])
        except Exception:
            errors.append((i, "amount/vat_rate non numerici"))
print(errors or "OK")
```

## File template
- CSV: `templates/baseline_template.csv`
