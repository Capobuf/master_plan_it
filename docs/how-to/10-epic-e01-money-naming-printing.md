# How-to: Implementare EPIC MPIT-E01 (Naming + VAT + Annualizzazione + Stampa) — Frappe v15

**Scopo:** applicare in modo deterministico (versionabile in repo) le regole di business concordate:

- Naming leggibile basato su `Year` del Budget: `BUD-2025-01`
- UX: usare **Title Field** nei link (vedere titolo umano invece dell'ID)
- IVA per riga su **tutti** i campi Currency, con normalizzazione `net / vat / gross`
- Annualizzazione coerente per `Monthly / Quarterly / Annual / Custom / None`
- Stampa professionale per **Budget**, **Project** e **Report** (Query Report) senza CSS custom globale

> Questa guida è pensata per agent a “bassa memoria”: seguire l’ordine, rispettare le STOP conditions, aggiornare i file richiesti ad ogni step.

---

## 0) Preflight (OBBLIGATORIO)

### 0.1 Verifica versione Frappe/ERPNext
```bash
bench version
```
**STOP** se non sei su v15.

### 0.2 Baseline “green”
```bash
bench --site <site> execute master_plan_it.devtools.verify.run
bench --site <site> run-tests --app master_plan_it
```
**STOP** se fallisce: prima ripristina baseline.

### 0.3 Leggi contesto repo (non saltare)
- `master/how-to/01-apply-changes.md`
- `master/reference/06-dev-workflow.md`
- `master/reference/01-data-model.md`
- `master/adr/0006-no-frontend-custom-scheduler.md` (vincolo: niente custom frontend/CSS)

---

## 1) Inventario *completo* dei Currency fields (anti-dimenticanze)

### 1.1 Trova tutti i Currency nei doctype spec (canonico)
```bash
rg '"fieldtype": "Currency"' apps/master_plan_it/spec/doctypes -n
```

Crea una lista (nel PR o in un file `COHERENCE_CHECK.md`) con:
- Doctype
- fieldname Currency
- se è parent o child table

**STOP** se trovi anche un solo Currency non coperto allo step 5 (VAT) e/o 6–7 (annual/totals).

---

## 2) Aggiungi preferenze per-utente: `MPIT User Preferences`

**Regole concordate:**
- Nessun default IVA a livello tenant.
- Defaults sono *per user*.
- Strict VAT: se importo != 0 e non trovi VAT rate né su riga né nelle preferenze utente → blocca salvataggio.

### 2.1 Crea Doctype (file-first)
Crea:
- `apps/master_plan_it/spec/doctypes/mpit_user_preferences.json`

Campi minimi richiesti:
- `user` (Link → User, reqd, unique)
- `default_vat_rate` (Percent, **NOT reqd**, ma se presente può essere 0)
- `default_amount_includes_vat` (Check, default 0)
- `show_attachments_in_print` (Check, default 0)

**Naming preferences (richiesto dalla tua decisione “in preferenze utente”):**
- `budget_prefix` (Data, default `BUD-`)
- `budget_sequence_digits` (Int, default 2)
- `project_prefix` (Data, default `PRJ-`)
- `project_sequence_digits` (Int, default 4)
- (opzionale, ma consigliato) prefissi per Actual/Baseline/Amendment

Permessi:
- impostare Owner = `user`
- consentire **lettura/scrittura solo if_owner** per i ruoli interni (es. `vCIO Manager`, `Client Editor`), così:
  - il print template può leggere le preferenze dell’utente corrente senza `ignore_permissions`
  - nessun dato “di altri utenti” è leggibile

> Se non riesci a esprimere “solo if_owner” nel doctype JSON in modo corretto, **STOP** e sistemare permessi prima di proseguire.

### 2.2 Helper backend (obbligatorio, no drift)
Crea:
- `apps/master_plan_it/master_plan_it/mpit_user_prefs.py`

Funzioni richieste:
- `get_or_create(user: str | None = None) -> Document`
- `get_default_vat_rate(user) -> float | None`
- `get_default_includes_vat(user) -> bool`
- `get_budget_series(user, year) -> (prefix, digits, middle)`
- `get_project_series(user) -> (prefix, digits)`

**STOP** se l’agent sta per duplicare logica “prefs” in 5 controller: deve essere centralizzata qui.

### 2.3 Applica e verifica
```bash
bench --site <site> execute master_plan_it.devtools.sync.sync_all
bench --site <site> migrate
bench --site <site> clear-cache
```

Verifica:
```bash
bench --site <site> execute frappe.db.exists --kwargs '{"doctype":"DocType","docname":"MPIT User Preferences"}'
```

---

## 3) UX: Title Field e “Show Title in Link Fields”

Obiettivo: nei Link fields vedere titoli umani, non solo codici.

### 3.1 Imposta nel doctype spec
Per i Doctype user-facing (minimo: Budget, Project; opzionali: Contract, Vendor se ha title), imposta:
- `title_field` (es. `"title"`)
- `show_title_field_in_link = 1`

File tipici:
- `apps/master_plan_it/spec/doctypes/mpit_budget.json`
- `apps/master_plan_it/spec/doctypes/mpit_project.json`
- ecc.

### 3.2 Applica e verifica
```bash
bench --site <site> execute master_plan_it.devtools.sync.sync_all
bench --site <site> migrate
bench --site <site> clear-cache
```

Verifica manuale:
- in un Budget, apri un Link (es. Project) e assicurati che la dropdown mostri il titolo.

---

## 4) Naming deterministico (Budget basato su Year)

**Regola chiusa:**
- Budget: `BUD-{Budget.year}-{NN}` con `{Budget.year}` preso dal campo **Year** del Budget (Link a MPIT Year)

### 4.1 Implementazione (server-side, Frappe v15)
Implementa `autoname(self)` nei controller:
- `apps/master_plan_it/master_plan_it/doctype/mpit_budget/mpit_budget.py`
- `apps/master_plan_it/master_plan_it/doctype/mpit_project/mpit_project.py`
- (estendere a: Actual Entry, Baseline Expense, Amendments e Quote)

Usa:
- `frappe.model.naming.getseries(prefix, digits)` (serie deterministica)

Usa prefissi/digits da `MPIT User Preferences`:
- Budget: `budget_prefix` + `{year}-` + getseries
- Project: `project_prefix` + getseries

**Validazione obbligatoria:**
- se `budget.year` è vuoto → `frappe.throw()` (non salvare)

### 4.2 Verifica end-to-end
1) Crea Budget Year=2025 → deve essere `BUD-2025-01`
2) Crea secondo Budget 2025 → `BUD-2025-02`
3) Crea Project → `PRJ-0001`

**STOP** se i nomi non matchano.

---

## 5) IVA su tutti i Currency fields (Strict VAT + net/vat/gross)

### 5.1 Regole (strict VAT mode)
Se `amount != 0`:
- `vat_rate` deve esistere (0 valido)
- se manca su riga **e** manca in User Prefs → blocca

Se `amount == 0`: ok rate vuoto, ma consigliato 0.

### 5.2 Pattern campi (per ogni Currency base)
Per un Currency `amount` aggiungere:
- `amount_includes_vat` (Check)
- `vat_rate` (Percent) *(oppure `<base>_vat_rate` se ci sono più importi nella stessa riga)*
- `amount_net` (Currency, read_only)
- `amount_vat` (Currency, read_only)
- `amount_gross` (Currency, read_only)

Ripetere per **tutti** i Currency individuati allo step 1 (parent e child).

**STOP** se manca anche solo un set di campi.

### 5.3 Helper unico (obbligatorio)
Crea:
- `apps/master_plan_it/master_plan_it/tax.py`

Funzione:
- `split_net_vat_gross(amount, vat_rate_pct, includes_vat, precision=2) -> (net, vat, gross)`

### 5.4 Applicazione nei Doctype (server-side)
Per ciascun Doctype con Currency:
- `validate(self)` calcola e salva net/vat/gross
- per child table, il calcolo va fatto nel parent `validate()` iterando le righe

**STOP** se l’agent propone JS form script per calcolo: vietato da ADR 0006.

### 5.5 Patch di backfill (anti-regressione)
Crea una patch che:
- per documenti esistenti, imposti:
  - includes_vat=0 se mancante
  - vat_rate=0 se mancante
  - net/gross=amount, vat=0
- (non inventare IVA storica: non esiste, quindi 0 è l’unico fallback non arbitrario)

Registra in `apps/master_plan_it/patches.txt`, poi:
```bash
bench --site <site> migrate
```

---

## 6) Annualizzazione (Budget Line + Baseline Expense)

### 6.1 Regole (chiuse)
Recurrence: `Monthly, Quarterly, Annual, Custom, None`

- None = **one-off** (annual = amount se overlap > 0)
- Custom = **Option A**: richiede `custom_period_months` > 0
  - annual = amount * (overlap_months / custom_period_months)
- Overlap mesi:
  - se date non overlappano l’anno del Budget.year → **blocca** (regola A)

### 6.2 Campi richiesti
Budget Line (child):
- `custom_period_months` (Int)
- `annual_net`, `annual_vat`, `annual_gross` (Currency, read_only)

Baseline Expense:
- `custom_period_months` (Int)
- `annual_net`, `annual_vat`, `annual_gross` (Currency, read_only)

### 6.3 Helper unico
Crea:
- `apps/master_plan_it/master_plan_it/annualization.py`

Funzioni:
- `get_year_bounds(year_name) -> (start_date, end_date)`
- `overlap_months(year_start, year_end, start, end) -> int`
- `annualize(amount, recurrence, overlap_months, custom_period_months=None) -> float`

### 6.4 Verifica end-to-end
- Monthly 100 (net), overlap 12 → annual_net=1200
- Quarterly 100, overlap 12 → 400
- Annual 1200, overlap 12 → 1200
- None 500, overlap>0 → 500
- Custom 600, custom_period_months=6, overlap 12 → 1200
- Date fuori anno → errore, non salvare

---

## 7) Totali net/vat/gross (Budget, Amendment, Project)

Aggiungi e calcola:
- Budget: `total_annual_net/vat/gross` = somma righe annual_*
- Amendment: `total_delta_*` = somma delta_*
- Project: `total_planned_*` = somma allocations planned_*

---

## 8) Report: usare i nuovi campi (fallback COALESCE)

Regola anti-regressione:
- Budget: `COALESCE(annual_net, amount)`
- Actual: `COALESCE(amount_net, amount)`
- Project planned: `COALESCE(planned_net, planned_amount)`
- ecc.

Inoltre:
- impostare `width` sulle colonne (report columns dict) per una tabella leggibile.

Verifica:
- report funzionano con dati storici e nuovi
- totali e variance coerenti

---

## 9) Stampa professionale (versionata in repo)

### 9.1 Print Formats (DocType) — Jinja
Crea:
- `MPIT Budget (Professional)`
- `MPIT Project (Professional)`

**Personalizzazione per-utente attachments (richiesta):**
- nella Print Format, mostra allegati **solo se** `MPIT User Preferences.show_attachments_in_print == 1`
- allegati come lista da `File` (no preview PDF come immagine)

**Versionamento in repo (obbligatorio):**
- assicurati che le Print Format siano `Standard = Yes`
- Developer mode attivo
- verifica che il file venga esportato in:
  - `apps/master_plan_it/master_plan_it/print_format/<print_format>/<print_format>.json`

**STOP** se il file non compare nel filesystem: stai creando configurazione solo nel DB (drift).

### 9.2 Report Print Formats — file `{report}.html`
Per ogni report MPIT crea:
- `apps/master_plan_it/master_plan_it/report/<report>/<report>.html`

Regole:
- non è Jinja: è microtemplating JS
- NON usare apici singoli `'`
- usa bootstrap classes

Verifica:
- Print del report produce PDF pulito e con intestazione

---

## 10) Merge gate (finale)

Comandi:
```bash
bench --site <site> execute master_plan_it.devtools.sync.sync_all
bench --site <site> migrate
bench --site <site> clear-cache
bench --site <site> execute master_plan_it.devtools.verify.run
bench --site <site> run-tests --app master_plan_it
```

Verifica manuale:
- Budget 2025 → `BUD-2025-01`
- Link fields mostrano Title
- Strict VAT: non salva se amount!=0 e vat_rate mancante (e prefs senza default)
- Annualizzazione: Monthly/Quarterly/Custom/None
- Print Budget/Project: layout pulito, attachments come lista
- Print report: usa `.html`

Aggiorna documentazione di progetto (file esistenti):
- `master/reference/01-data-model.md`
- `master/reference/04-reports-dashboards.md`
- `master/reference/08-known-gaps.md`
