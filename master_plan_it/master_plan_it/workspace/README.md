# Workspace Master Plan IT

Policy breve:
- Usare `links` per la navigazione strutturata (DocTypes, Reports, ecc.).
- Tenere `shortcuts` minime e orientate ad azioni rapidi (es. la nuova pagina `MPIT Dashboard`, report più usati).
- Evitare di duplicare lo stesso collegamento sia in `links` che in `shortcuts` per ridurre drift.

Modifiche effettuate:
- Aggiornato il primo shortcut verso la nuova pagina `MPIT Dashboard` (`/app/mpit-dashboard`) che sostituisce la vecchia dashboard.
- Rimossi i riferimenti inline ai charts dal `content` (ora Quick Actions registrano solamente le create action principali).
- Aggiornati i nomi dei chart per coerenza con i report: `MPIT Budget vs Actual (Approved)` → `MPIT Baseline vs Exceptions`, `MPIT Current Budget vs Actual` → `MPIT Current Plan vs Exceptions`, `MPIT Renewals by Month` → `MPIT Renewals Window (by Month)`.

Verifica suggerita:
1. `bench --site <site> migrate`
2. `bench --site <site> clear-cache`
3. `bench --site <site> execute master_plan_it.devtools.verify.run`
4. Controllare la vista Desk per l'app Master Plan IT e verificare che le voci visualizzate rispecchino le intenzioni.

Se vuoi, preparo la PR con le modifiche e la descrizione per il reviewer.
