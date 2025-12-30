# Budget Import Template (Budget Lines)

Obiettivo: raccogliere righe Budget pronte per l’inserimento manuale, con gli stessi campi chiave del doctype.

## Come usare il template
1. Scarica `templates/budget_template.csv`.
2. Compila le colonne seguendo le regole sotto.
3. (Opzionale) esegui il preflight rapido in `bench console` prima di inserire i dati in UI.

## Colonne e regole
- `budget` (obbligatorio): nome del Budget target (es. `BUD-2025-01`).
- `category` (obbligatorio): nome categoria.
- `vendor` (facoltativo): nome vendor.
- `description` (obbligatorio): testo breve.
- `recurrence_rule` (obbligatorio): `Monthly` | `Quarterly` | `Annual` | `None`.
- `amount` (obbligatorio): importo della linea (net o gross in base alla colonna successiva).
- `amount_includes_vat` (obbligatorio): `1` se l’importo include IVA, `0` altrimenti.
- `vat_rate` (obbligatorio): percentuale IVA (es. `22`), `0` per esenzione.
- `period_start_date` / `period_end_date` (facoltativi): date ISO `YYYY-MM-DD`; usate per l’overlap con l’anno MPIT.
- `contract` / `project` (facoltativi): link ai rispettivi record se già esistenti.

## Esempi validi
- OPEX mensile: budget `BUD-2025-01`, category Connectivity, vendor TIM, 104 €/mese, includes=1, vat_rate=22, recurrence_rule=Monthly.
- CAPEX annuale senza IVA: budget `BUD-2025-02`, category Hardware, vendor vuoto, 5000 €, includes=0, vat_rate=0, recurrence_rule=Annual.

## Preflight rapido (opzionale)
```python
import csv
path = "/path/al/file/budget_template.csv"
errors = []
with open(path) as f:
    reader = csv.DictReader(f)
    for i, row in enumerate(reader, start=2):
        if not row["budget"]:
            errors.append((i, "budget mancante"))
        if not row["category"]:
            errors.append((i, "category mancante"))
        try:
            float(row["amount"])
            float(row["vat_rate"])
        except Exception:
            errors.append((i, "amount/vat_rate non numerici"))
print(errors or "OK")
```

## File template
- CSV: `templates/budget_template.csv`
