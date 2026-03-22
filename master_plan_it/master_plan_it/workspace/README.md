# Workspace Master Plan IT

Policy breve:
- Usare `links` per la navigazione strutturata (DocTypes, Reports, ecc.).
- Tenere `shortcuts` minime e orientate ad azioni rapidi (es. la nuova pagina `MPIT Dashboard`, report più usati).
- Evitare di duplicare lo stesso collegamento sia in `links` che in `shortcuts` per ridurre drift.

Modifiche effettuate:
- Aggiornato il primo shortcut per puntare alla Dashboard standard ("Master Plan IT Overview").
- Rimossi i riferimenti inline ai charts dal `content` (ora Quick Actions registrano solamente le create action principali).
- Aggiornati i nomi dei chart per coerenza con i report: `MPIT Budget vs Actual (Approved)` → `MPIT Baseline vs Exceptions`, `MPIT Current Budget vs Actual` → `MPIT Current Plan vs Exceptions`, `MPIT Renewals by Month` → `MPIT Renewals Window (by Month)`.

Verifica suggerita:
1. `bench --site <site> migrate`
2. `bench --site <site> clear-cache`
3. `bench --site <site> execute master_plan_it.devtools.verify.run`
4. Controllare la vista Desk per l'app Master Plan IT e verificare che le voci visualizzate rispecchino le intenzioni.

Layout "Azioni Rapide":
- Prime 3 shortcut (Nuova Spesa, Nuovo Progetto, Nuovo Contratto) = create actions → col 4 (tre colonne simmetriche da 4/12).
- Successive 4 shortcut (Panoramica, Piano Mensile, Rinnovi, What-If) = report/analysis shortcuts → col 3 (quattro colonne da 3/12).
- Due righe bilanciate 3+4; label leggibili senza troncamento.
- Nessun custom CSS/JS: solo configurazione nativa del campo `col` nel `content` JSON del workspace.

Se vuoi, preparo la PR con le modifiche e la descrizione per il reviewer.
