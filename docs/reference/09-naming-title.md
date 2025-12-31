# Reference: Naming + Title Field (Frappe v15)

Questa reference definisce **come** implementare naming deterministico e UX migliore senza frontend custom.

---

## 1) Naming (ID / `name`) in Frappe

Ogni documento ha un campo `name` come chiave primaria.
I modi supportati per controllare `name` includono:
- Naming Rules
- Metodo Python `autoname(self)` nel controller del DocType
- Proprietà DocType `autoname`

Per MPIT usiamo `autoname(self)` perché è:
- deterministico
- versionabile
- indipendente dalla configurazione UI del tenant

### 1.1 Implementazione consigliata
- crea `master_plan_it/master_plan_it/naming.py`
- usa `frappe.model.naming.getseries(prefix, digits)`

### 1.2 Regola Budget (chiusa)
- `name`: `BUD-{Budget.year}-{NN}`
- `Budget.year` è *obbligatorio* e viene dal campo Year del Budget (Link a MPIT Year)

### 1.3 Regola Project (chiusa)
- `name`: `PRJ-{NNNN}`

### 1.4 Preferenze per-utente sul naming (richiesto)
In `MPIT User Preferences` definisci:
- `budget_prefix`, `budget_sequence_digits`
- `project_prefix`, `project_sequence_digits`

Budget mantiene la parte `{year}-` obbligatoria; le preferenze controllano prefix e digits.

---

## 2) Title Field (UX)

### 2.1 `title_field`
Impostare nel DocType:
- `title_field = "title"` (o il campo umano)

### 2.2 `show_title_field_in_link`
Impostare:
- `show_title_field_in_link = 1`

Risultato:
- nei Link fields si vede il titolo (umano), non solo `name` (ID serie)

---

## 3) Anti-regressione
- Non rinominare documenti esistenti.
- Naming deterministico vale per nuovi documenti.
- Se un record storico ha `name` random, deve continuare a funzionare.
