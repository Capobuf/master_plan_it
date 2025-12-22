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
