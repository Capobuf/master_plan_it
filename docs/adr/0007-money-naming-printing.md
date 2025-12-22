# ADR 0007 — Money rules + Naming + Printing (MPIT v1, Frappe v15)

- Status: **Accepted**
- Date: 2025-12-22
- Owners: DOT / MPIT

---

## Context

Problemi riscontrati:
- `name` random (es. `ig0v0mikhb`) poco user-friendly
- assenza di normalizzazione IVA e totali net/lordo
- ricorrenze non annualizzate coerentemente
- stampe brutte e allegati PDF mostrati come “immagine rotta”
- vincolo: **no custom frontend/CSS** (ADR 0006)

---

## Decisions

### 1) Naming
- Budget `name`: `BUD-{Budget.year}-{NN}` (progressivo per anno)
- `{Budget.year}` è il campo Year del Budget (obbligatorio).
- Project `name`: `PRJ-{NNNN}` (progressivo globale)
- Le preferenze per-utente determinano `prefix` e `digits`, ma:
  - Budget deve includere sempre `{year}-` tra prefix e progressivo.

Non rinominare documenti esistenti.

### 2) Title Field (UX)
Per i DocType user-facing:
- impostare `title_field`
- impostare `show_title_field_in_link = 1`

### 3) VAT (Strict mode) su tutti gli importi Currency
- Tutti i Currency fields devono avere net/vat/gross.
- Se amount != 0:
  - vat_rate obbligatorio (0 valido)
  - se manca su riga e manca in User Preferences → blocca

Nessun default IVA tenant.

### 4) Annualizzazione
Recurrence: `Monthly, Quarterly, Annual, Custom, None`

- None = one-off
- Custom = Option A: richiede `custom_period_months` > 0
  - annual = amount * (overlap_months / custom_period_months)
- Date fuori anno (overlap = 0) → blocco salvataggio (regola A)

Normalizzare net/vat/gross prima di annualizzare.

### 5) Printing
- Doc Print Formats: Jinja + bootstrap classes, versionate come Standard (file in repo)
- Allegati: lista di file (no preview PDF come immagini)
- Report Print Formats: `{report}.html` (microtemplating), no single quotes

---

## Consequences

- Nuovi campi (net/vat/gross, annual_*, totals) sui DocType esistenti.
- Patch di backfill per dati storici (vat_rate=0, includes_vat=0).
- Report con fallback `COALESCE(new, old)`.
- Test + checklist manuale stampa.

