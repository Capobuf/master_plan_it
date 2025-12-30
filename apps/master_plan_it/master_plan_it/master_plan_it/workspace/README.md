# Workspace Master Plan IT

Policy breve:
- Usare `links` per la navigazione strutturata (DocTypes, Reports, ecc.).
- Tenere `shortcuts` minime e orientate ad azioni rapidi (es. Overview dashboard, report più usati).
- Evitare di duplicare lo stesso collegamento sia in `links` che in `shortcuts` per ridurre drift.

Modifiche effettuate:
 - Consolidati i `shortcuts` (preservato `Overview Dashboard` e il report `Current Plan vs Exceptions`); rimosse voci duplicate (Budgets, Projects, Exceptions/Allowance Entries, Contracts, Vendors, Categories, User Preferences).
- Rimossi i riferimenti inline ai charts dal `content` (ora si usa il dashboard canonico `Master Plan IT Overview`).
- Aggiornati i nomi dei chart per coerenza con i report: `MPIT Budget vs Actual (Approved)` → `MPIT Baseline vs Exceptions`, `MPIT Current Budget vs Actual` → `MPIT Current Plan vs Exceptions`, `MPIT Renewals by Month` → `MPIT Renewals Window (by Month)`.

Verifica suggerita:
1. `bench --site <site> migrate`
2. `bench --site <site> clear-cache`
3. `bench --site <site> execute master_plan_it.devtools.verify.run`
4. Controllare la vista Desk per l'app Master Plan IT e verificare che le voci visualizzate rispecchino le intenzioni.

Se vuoi, preparo la PR con le modifiche e la descrizione per il reviewer.
