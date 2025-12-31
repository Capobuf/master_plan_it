# How to load demo data (manual, optional)

Questi dati demo non vengono caricati automaticamente: servono solo per test locali V2.

## Prerequisiti
- Ambiente bench attivo.
- Ruoli MPIT presenti (bootstrap standard).

## Comando
Esegui una sola volta (idempotente; crea un nuovo Forecast ogni run):
```bash
bench --site <tuo_sito> execute master_plan_it.devtools.demo_data.run --kwargs '{"year":"2025"}'
```

## Cosa crea
- Master data: 2 categorie (`ZZZ Demo Category A/B`), 2 vendor, cost center root + 2 leaf.
- Contratti: `CT-ZZZ-MONTHLY` (mensile, VAT inclusa), `CT-ZZZ-QUARTERLY` (trimestrale).
- Progetti: `PRJ-ZZZ-APPROVED` (quote Approved), `PRJ-ZZZ-PLANNED` (solo allocazioni).
- Actual Entry: 2 Delta (uno progetto, uno contratto), 1 Allowance Spend.
- Budget Forecast per l’anno passato (`BUD-...`), generato via `refresh_from_sources` con righe da contratti e progetti.
- Naming: usa i prefissi/digits configurati in `MPIT Settings` o nelle tue MPIT User Preferences; cambiare prefisso non rinomina record esistenti.

## Note
- Il comando assegna il ruolo `vCIO Manager` all’utente corrente per approvare le quote demo.
- Ogni esecuzione crea un nuovo Budget Forecast; elimina quelli vecchi se non servono.
- Non viene attivato nessun Forecast automaticamente.
