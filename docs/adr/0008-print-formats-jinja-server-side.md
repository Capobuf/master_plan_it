# ADR 0008: Print Formats Server-Side con Jinja (No Custom Frontend)

**Status:** Accepted  
**Date:** 2025-12-22  
**Deciders:** Development Team  
**Technical Story:** EPIC MPIT-E01 Phase 6 (Printing)

---

## Context

MPIT richiede funzionalità di stampa professionale per:
- Budget documents (MPIT Budget)
- Project documents (MPIT Project)  
- Query Reports (4 report: Approved Budget vs Actual, Current Budget vs Actual, Projects Planned vs Actual, Renewals Window)

Frappe v15 supporta due approcci per printing:
1. **Print Format Builder** (drag-and-drop UI)
2. **Custom Print Formats** (Jinja templates o JS microtemplating)

Vincoli architetturali esistenti:
- ADR 0006: NO custom frontend/JavaScript/CSS pipelines
- ADR 0002: Desk-only (no portal/Website Users)
- Tutti i metadata devono essere versionabili in git (no drift)

## Decision

Implementare printing usando:

### 1. Doc Print Formats (Budget, Project)
- **Jinja templates** (server-side rendering)
- File versionati: `{print_format}.json` + `{print_format}.html`
- `Standard = Yes` per export automatico in filesystem
- Embedded CSS inline (Bootstrap-inspired classes)
- NO JavaScript lato client
- User preferences integration per conditional rendering (es. attachments)

### 2. Report HTML Templates (4 Query Reports)  
- **Microtemplating** (framework Frappe integrato, sintassi `<%= %>`)
- File versionati: `{report}.html` nella directory del report
- Solo HTML + embedded CSS
- NO apici singoli (`'`) nel template (limitazione microtemplating)
- Bootstrap classes standard per styling

### 3. Attachments Rendering
- NO preview PDF come `<img>` (causa broken images)
- Lista testuale con link: `File.file_name` + `File.file_url`
- Conditional display basato su `MPIT User Preferences.show_attachments_in_print`

### 4. Sync e Import Process
- Print Formats: import via `frappe.modules.import_file.import_file_by_path()`
- Report HTML: auto-detected da Frappe (solo creare file nella directory)
- Esecuzione in `master_plan_it.devtools.sync.sync_all` (idempotente)

## Consequences

### Positive
✅ **Zero Drift:** Tutti i template versionati in git  
✅ **Compliance ADR 0006:** No custom frontend/build pipeline  
✅ **Maintainability:** Template leggibili, debugging facile (view source HTML)  
✅ **Performance:** Server-side rendering (no client-side overhead)  
✅ **User Preferences:** Conditional rendering basato su preferenze utente  
✅ **Testability:** Script automatico di test (`test_print.py`)  

### Negative
⚠️ **Limited Styling:** Solo Bootstrap classes + embedded CSS inline  
⚠️ **No Dynamic UI:** No JavaScript interattivo (ma non necessario per stampa)  
⚠️ **Microtemplating Quirks:** Report templates con sintassi JS-like ma limitazioni (no single quotes)

### Neutral
ℹ️ **Dual JSON/HTML Files:** Ogni print format richiede 2 file (metadata + template)  
ℹ️ **Import Manual Step:** Print Formats richiedono import esplicito (report no)

## Implementation Notes

### Print Format JSON Structure
```json
{
  "doctype": "Print Format",
  "name": "MPIT Budget Professional",
  "doc_type": "MPIT Budget",
  "module": "Master Plan IT",
  "standard": "Yes",
  "print_format_type": "Jinja",
  "custom_format": 1,
  "disabled": 0,
  "margin_top": 15.0,
  "margin_bottom": 15.0,
  "margin_left": 15.0,
  "margin_right": 15.0
}
```

**CRITICAL:** NON includere campi `format_data` (array) o `html` (string) nel JSON. Causano errori di validazione "Value for Format Data cannot be a list". Il contenuto HTML va nel file `.html` separato.

### User Preferences Integration Pattern
```jinja
{% set user_prefs = frappe.get_doc("MPIT User Preferences", frappe.session.user) 
   if frappe.db.exists("MPIT User Preferences", frappe.session.user) else None %}
{% set show_attachments = user_prefs.show_attachments_in_print if user_prefs else 0 %}

{% if show_attachments %}
  {# Render attachments list #}
{% endif %}
```

### Report Microtemplating Pattern
```html
<% if (filters.year) { %>
  <strong>Year:</strong> <%= filters.year %> &nbsp;
<% } %>

<% for(var i = 0; i < data.length; i++) { %>
  <% var row = data[i]; %>
  <tr>
    <td><%= row.category %></td>
    <td class="text-right <%= row.variance < 0 ? 'text-danger' : 'text-success' %>">
      <%= frappe.format(row.variance, {fieldtype: "Currency"}) %>
    </td>
  </tr>
<% } %>
```

### Allowed Bootstrap Classes
- Layout: `.container`, `.row`, `.col-*`
- Typography: `.text-right`, `.text-center`, `.text-muted`, `.text-danger`, `.text-success`
- Tables: `.table`, `.table-bordered`, `.table-sm`, `.table-striped`
- Badges: `.badge`, `.badge-*` (success, warning, danger, secondary)
- Spacing: `.mb-*`, `.mt-*`, `.p-*`

### Testing Strategy
Script: `apps/master_plan_it/master_plan_it/devtools/test_print.py`

Verifica:
1. Fixtures setup (Category, Vendor, Year)
2. Print Formats import via `import_file_by_path()`
3. Budget creation con VAT + annualization
4. Print Formats availability check in database
5. Visual inspection: genera PDF e verifica layout

## References

- ADR 0006: No Frontend Custom/Scheduler
- ADR 0002: Desk-Only
- Frappe Print Format Docs: https://frappeframework.com/docs/v15/user/en/desk/print-format
- Bootstrap 4 Docs: https://getbootstrap.com/docs/4.6/

## Related Changes

Files created/modified:
- `apps/master_plan_it/master_plan_it/print_format/mpit_budget_professional.{json,html}`
- `apps/master_plan_it/master_plan_it/print_format/mpit_project_professional.{json,html}`
- `apps/master_plan_it/master_plan_it/report/*/mpit_*.html` (4 files)
- `apps/master_plan_it/master_plan_it/devtools/test_print.py`
- Docs: `docs/how-to/10-epic-e01-money-naming-printing.md` (Phase 6 section)
- Docs: `docs/reference/11-printing-v15.md` (Implementation details)
- Docs: `docs/reference/10-money-vat-annualization.md` (Dual-mode controller)
