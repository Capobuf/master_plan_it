# Reference: Printing (Doc Print Formats + Report Print Formats) — Frappe v15

Obiettivo: PDF professionali senza CSS custom globale.

---

## 1) Doc Print Formats (Jinja)

### 1.1 Versionamento (obbligatorio)
Per evitare drift:
- crea Print Format con `Standard = Yes`
- assicurati che venga esportata nel filesystem:

`apps/master_plan_it/master_plan_it/print_format/<print_format>/<print_format>.json`

**STOP** se il file non viene creato: stai salvando solo nel DB.

### 1.2 Allegati PDF (fix “immagine rotta”)
Non renderizzare PDF come `<img>`.
Mostra lista di `File` (nome + URL).

Esempio Jinja:
```jinja
{% set files = frappe.get_all("File",
    filters={"attached_to_doctype": doc.doctype, "attached_to_name": doc.name},
    fields=["file_name","file_url"],
    order_by="creation asc"
) %}

{% if files %}
  <h4>Attachments</h4>
  <ul>
    {% for f in files %}
      <li>{{ f.file_name }} — {{ f.file_url }}</li>
    {% endfor %}
  </ul>
{% endif %}
```

### 1.3 Personalizzazione per-utente (attachments)
Nel template:
- recupera `MPIT User Preferences` dell’utente corrente (via helper backend)
- se `show_attachments_in_print` è 1 → mostra lista, altrimenti no

---

## 2) Report Print Formats (file `{report}.html`)

### 2.1 Dove va il file
Per `mpit_current_budget_vs_actual`:
`apps/master_plan_it/master_plan_it/report/mpit_current_budget_vs_actual/mpit_current_budget_vs_actual.html`

### 2.2 Importante: non è Jinja
È microtemplating JS in Desk.

Regole:
- NON usare apici singoli `'` nel template
- usa solo doppi apici `"`

Esempio robusto:
```html
<div class="container">
  <h2><%= __("MPIT Current Budget vs Actual") %></h2>
  <p class="text-muted"><%= __("Generated on") %>: <%= frappe.datetime.now_datetime() %></p>

  <table class="table table-bordered table-sm">
    <thead>
      <tr>
        <% for (var i = 0; i < columns.length; i++) { %>
          <th><%= columns[i].label %></th>
        <% } %>
      </tr>
    </thead>
    <tbody>
      <% for (var r = 0; r < data.length; r++) { %>
        <tr>
          <% for (var c = 0; c < columns.length; c++) { %>
            <td><%= data[r][columns[c].fieldname] %></td>
          <% } %>
        </tr>
      <% } %>
    </tbody>
  </table>
</div>
```

---

## 3) Implementazione MPIT (Dec 2025) ✅

### 3.1 Print Formats Doc (Jinja)

Creati 2 formati:

**MPIT Budget Professional**
Path: `apps/master_plan_it/master_plan_it/print_format/mpit_budget_professional.{json,html}`

Template structure:
```jinja
{# User preferences integration #}
{% set user_prefs = frappe.get_doc("MPIT User Preferences", frappe.session.user) 
   if frappe.db.exists("MPIT User Preferences", frappe.session.user) else None %}
{% set show_attachments = user_prefs.show_attachments_in_print if user_prefs else 0 %}

<!DOCTYPE html>
<html>
<head>
  <style>
    /* Embedded CSS - Bootstrap-inspired classes */
    .report-header { border-bottom: 2px solid #333; padding-bottom: 10px; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .table { width: 100%; border-collapse: collapse; }
    /* ... */
  </style>
</head>
<body>
  <div class="report-header">
    <h1>Budget {{ doc.name }}</h1>
    <p>{{ doc.title }}</p>
  </div>
  
  <div class="info-grid">
    <div><strong>Year:</strong> {{ doc.year }}</div>
    <div><strong>Status:</strong> {{ doc.workflow_state or "Draft" }}</div>
  </div>
  
  <table class="table">
    <thead>
      <tr>
        <th>Category</th><th>Vendor</th><th>Description</th>
        <th class="text-right">Net</th><th class="text-right">VAT</th><th class="text-right">Gross</th>
        <th>Recurrence</th>
      </tr>
    </thead>
    <tbody>
      {% for line in doc.lines %}
        <tr>
          <td>{{ line.category }}</td>
          <td>{{ line.vendor }}</td>
          <td>{{ line.description }}</td>
          <td class="text-right">{{ frappe.format(line.amount_net, {"fieldtype": "Currency"}) }}</td>
          <td class="text-right">{{ frappe.format(line.amount_vat, {"fieldtype": "Currency"}) }}</td>
          <td class="text-right">{{ frappe.format(line.amount_gross, {"fieldtype": "Currency"}) }}</td>
          <td>{{ line.recurrence_rule }}</td>
        </tr>
      {% endfor %}
    </tbody>
  </table>
  
  {# Conditional attachments based on user preference #}
  {% if show_attachments %}
    {% set files = frappe.get_all("File",
        filters={"attached_to_doctype": doc.doctype, "attached_to_name": doc.name},
        fields=["file_name","file_url"],
        order_by="creation asc"
    ) %}
    {% if files %}
      <h4>Attachments</h4>
      <ul>
        {% for f in files %}
          <li>{{ f.file_name }} — <a href="{{ f.file_url }}">{{ f.file_url }}</a></li>
        {% endfor %}
      </ul>
    {% endif %}
  {% endif %}
</body>
</html>
```

**MPIT Project Professional**
Similar structure, con sezioni multiple:
- Header con name/title
- Info grid (status, start_date, end_date, description)
- Allocations table (year, budget, planned_net/vat/gross)
- Quotes table (vendor, quote_date, quote_net/vat/gross)
- Totals per section

JSON metadata comune:
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

**CRITICAL:** Rimuovere campi `format_data` e `html` dal JSON (causano errori di validazione). Il contenuto HTML va nel file `.html` separato.

### 3.2 Report HTML Templates (Microtemplating)

Creati 4 template per Query Reports:

**1. Approved Budget vs Actual**
Path: `apps/master_plan_it/master_plan_it/report/mpit_approved_budget_vs_actual/mpit_approved_budget_vs_actual.html`

Features:
- Filter display dinamico (Year, Category, Vendor)
- 7-column table con variance color coding
- Footer con timestamp generazione

Variance styling:
```html
<td class="text-right <%= row.variance < 0 ? 'text-danger' : 'text-success' %>">
  <%= frappe.format(row.variance, {fieldtype: "Currency"}) %>
</td>
```

**2. Current Budget vs Actual**
9 colonne: Baseline + Amendments + Current + Actual + Variance
Amendment delta con color coding (red=increase, green=decrease)

**3. Projects Planned vs Actual**
Status badges con colori semantici:
```html
<% 
  var badgeClass = "";
  if (row.status == "Planning") badgeClass = "badge-warning";
  else if (row.status == "Active") badgeClass = "badge-success";
  else if (row.status == "Completed") badgeClass = "badge-secondary";
  else if (row.status == "Cancelled") badgeClass = "badge-danger";
%>
<span class="badge <%= badgeClass %>"><%= row.status %></span>
```

**4. Renewals Window**
Urgency-based badges:
- Expired (days < 0): red
- Urgent (≤30 days): orange
- Soon (≤60 days): yellow  
- Normal (>60 days): blue

### 3.3 Import e Sync

Per importare Print Formats in database:
```python
from frappe.modules.import_file import import_file_by_path
import os

app_path = "/home/frappe/frappe-bench/apps/master_plan_it/master_plan_it/master_plan_it"
budget_pf = os.path.join(app_path, "print_format/mpit_budget_professional.json")

if os.path.exists(budget_pf):
    import_file_by_path(budget_pf)
    frappe.db.commit()
```

Report HTML templates sono auto-detected da Frappe (solo creare il file `.html` nella directory del report).

### 3.4 Styling Guidelines (ADR 0006 Compliance)

**Classi Bootstrap standard permesse:**
- Layout: `.container`, `.row`, `.col-*`
- Typography: `.text-right`, `.text-center`, `.text-muted`, `.text-danger`, `.text-success`
- Tables: `.table`, `.table-bordered`, `.table-sm`, `.table-striped`
- Badges: `.badge`, `.badge-success`, `.badge-warning`, `.badge-danger`, `.badge-secondary`
- Spacing: `.mb-*`, `.mt-*`, `.p-*`, `.mx-auto`

**NON permesso:**
- Custom CSS files esterni
- JavaScript lato client
- Framework frontend custom (React, Vue, ecc.)
- Build pipeline per asset

**Embedded CSS:** Consentito inline nel `<style>` tag dell'HTML, purché:
- Stile semplice e dichiarativo
- No JavaScript inline
- No dynamic CSS generation

### 3.5 Testing

Creato script di test: `apps/master_plan_it/master_plan_it/devtools/test_print.py`

Esegue:
1. Setup fixtures (Category, Vendor, Year)
2. Import print formats via `import_file_by_path()`
3. Creazione Budget test con VAT+annualization
4. Verifica print formats disponibili in database

Run:
```bash
bench --site <site> execute master_plan_it.devtools.test_print.run
```

Output atteso:
```
============================================================
MPIT Phase 6 - Print Format Testing
============================================================
✓ Fixtures created
✓ Imported Budget print format
✓ Imported Project print format

Print Formats for MPIT Budget (1 found):
  - MPIT Budget Professional (standard=Yes, html=NO)

✓ Created Budget: BUD-2025-04
  Line 1 Annual Net: 12000.0 (Monthly 1000×12)
  Line 2 Annual Net: 2000.0 (Quarterly 500×4)
============================================================
```

**Note:** `html=NO` indica che il campo `html` nel JSON è vuoto (corretto). Il contenuto HTML è nel file `.html` separato.
