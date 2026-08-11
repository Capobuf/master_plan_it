# Expenses API contract delta — Feature 020

Tutti gli endpoint restano Laravel Sanctum SPA, active-user, active-Tenant, tenant-context e ability protected. Gli identificatori di altro Tenant o fuori dal Planning Year richiesto falliscono closed senza data leakage. Gli importi restano stringhe decimali esatte calcolate dal backend.

## Post-review Expense row grid

`ExpenseRow.description` resta un campo obbligatorio `string`, massimo 255 caratteri, sia nelle request create/update sia nelle response detail. La composizione ERP lo presenta nei `Dettagli` della riga editor, ma non modifica il contratto API, la generazione Contract o la tabella read-only.

## `GET /api/v1/expenses`

Endpoint Register invariato; non viene creato un endpoint search separato.

### Query

| Parametro | Tipo | Default | Regola |
|---|---|---|---|
| `year` | positive integer | — | richiesto; Planning Year del Tenant e unico scope temporale |
| `kind` | `ordinary` \| `plafond` | null | Natura Expense |
| `q` | string, max 255 | null | titolo Expense case-insensitive; stringa vuota equivale a null |
| `cost_center_id` | positive integer | null | Cost Center del Tenant |
| `project_id` | positive integer | null | Project del Tenant |
| `contract_id` | positive integer | null | Contract del Tenant |
| `vendor_id` | positive integer | null | match se almeno una riga corrente/non eliminata usa il Vendor |
| `state` | `open` \| `closed` | null | stato Expense |
| `page` | positive integer | 1 | pagina corrente |
| `per_page` | integer 1–100 | 25 | il Register offre le scelte 25, 50 e 100; il range preserva i consumer API esistenti |

Cost Center, Project, Contract, Vendor o Planning Year inesistenti/estranei restituiscono il normale `404 RESOURCE_NOT_FOUND`. Enum e parametri malformati restituiscono `422 VALIDATION_FAILED`.

### Response

```json
{
  "data": [
    {
      "id": 101,
      "planning_year_id": 42,
      "planning_year_label": 2026,
      "cost_center_id": 7,
      "cost_center_name": "Infrastruttura",
      "kind": "ordinary",
      "state": "open",
      "title": "Licenze annuali",
      "project_id": 9,
      "project_title": "Modernizzazione",
      "project_current": true,
      "contract_id": 12,
      "contract_title": "Licenze software",
      "contract_current": true,
      "vendor_count": 2,
      "vendor_summary": "2 fornitori",
      "row_count": 3,
      "lock_version": 4,
      "totals": {
        "net": "1000.00",
        "vat": "220.00",
        "gross": "1220.00",
        "currency": "EUR",
        "official_basis": "net"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 25,
    "total": 1
  },
  "links": {
    "first": null,
    "last": null,
    "prev": null,
    "next": null
  },
  "totals": {
    "net": "1000.00",
    "vat": "220.00",
    "gross": "1220.00",
    "currency": "EUR",
    "official_basis": "net"
  },
  "column_preferences": [
    {"key": "kind", "visible": true},
    {"key": "contract", "visible": true},
    {"key": "project", "visible": true},
    {"key": "cost_center", "visible": true},
    {"key": "vendor", "visible": false},
    {"key": "net", "visible": true},
    {"key": "vat", "visible": true},
    {"key": "gross", "visible": true},
    {"key": "state", "visible": true}
  ]
}
```

`totals` usa tutte le Expense e tutte le righe correnti del dataset dopo l'applicazione di ogni filtro, prima della paginazione. Il filtro Vendor determina quali Expense appartengono al dataset ma non elimina dal totale le altre righe correnti delle Expense corrispondenti.

`vendor_count` conta Vendor distinti non nulli sulle righe correnti/non eliminate. `vendor_summary` vale `—` con zero Vendor, il nome con uno, `N fornitori` con più Vendor.

`year_options` può restare temporaneamente nella response per compatibilità, ma il Register Feature 020 non lo usa e non espone un anno locale.

## `GET /api/v1/expenses/{expense}`

### Query

| Parametro | Tipo | Default | Regola |
|---|---|---|---|
| `year` | positive integer | — | richiesto; deve coincidere con il Planning Year della Expense |

Una Expense dello stesso Tenant ma di altro anno restituisce `404 RESOURCE_NOT_FOUND`, come un ID estraneo o eliminato. Questo endpoint resta la fonte per Dettaglio, Editor ed espansione lazy.

### Response delta

La response corrente aggiunge:

```json
{
  "data": {
    "contract_id": 12,
    "contract_title": "Licenze software",
    "rows": [
      {
        "vendor_id": 31,
        "vendor_name": "Acme Italia"
      }
    ]
  }
}
```

`contract_title` e `vendor_name` sono label tenant-safe; sono `null` quando la relazione è assente. Tutti gli altri campi detail/row, inclusi valori economici, lock, lifecycle, generazione e revisioni, restano invariati.

## `PUT /api/v1/expenses/register-preferences`

Salva le preferenze della vista Expense Register per l'utente autenticato nel Tenant corrente. Richiede `expense.view`; l'utente può modificare soltanto le proprie preferenze.

### Request

```json
{
  "columns": [
    {"key": "kind", "visible": true},
    {"key": "vendor", "visible": true},
    {"key": "contract", "visible": false},
    {"key": "project", "visible": true},
    {"key": "cost_center", "visible": true},
    {"key": "net", "visible": true},
    {"key": "vat", "visible": false},
    {"key": "gross", "visible": true},
    {"key": "state", "visible": true}
  ]
}
```

Regole:

- ogni chiave corrente compare esattamente una volta;
- chiavi ammesse: `kind`, `contract`, `project`, `cost_center`, `vendor`, `net`, `vat`, `gross`, `state`;
- almeno una fra `net`, `vat`, `gross` è visibile;
- checkbox, expander, Spesa e azioni sono strutturali e non sono accettate nel payload;
- chiavi sconosciute in scrittura producono `422 VALIDATION_FAILED`;
- in lettura, chiavi obsolete già persistite vengono ignorate e nuove chiavi vengono aggiunte deterministicamente secondo il default.

### Response

`200 OK` con envelope `data.columns` contenente la configurazione normalizzata e persistita.

## `POST /api/v1/expenses/bulk-actions`

Esegue una sola mutazione su item selezionati nella pagina corrente. La request accetta da 1 a 100 ID distinti. L'intera operazione è atomica: ogni errore annulla ogni modifica, audit e destinazione creata nella request.

### Base request

```json
{
  "action": "close",
  "planning_year_id": 42,
  "items": [
    {"id": 101, "lock_version": 3},
    {"id": 102, "lock_version": 5}
  ]
}
```

Tutti gli item devono appartenere al Tenant e `planning_year_id` richiesti. Authorization e optimistic lock sono verificati per ogni record. ID duplicati, mancanti, estranei, fuori anno o stale rifiutano l'intera request.

### Close

Ability: `expense.update`.

```json
{
  "action": "close",
  "planning_year_id": 42,
  "items": [{"id": 101, "lock_version": 3}],
  "outcome": null
}
```

`outcome` è nullable oppure `not_incurred|cancelled` e viene applicato a tutti gli item. `moved` resta riservato al workflow Move. Tutti gli item devono essere aperti.

### Move

Abilities: `expense.update` e `expense.create`.

```json
{
  "action": "move",
  "planning_year_id": 42,
  "items": [{"id": 101, "lock_version": 3}],
  "target_planning_year_id": 43
}
```

Lo stesso anno destinazione attivo viene applicato a tutti gli item. Ogni Move crea la propria nuova Expense secondo le regole esistenti.

### Delete

Ability: `expense.delete`.

```json
{
  "action": "delete",
  "planning_year_id": 42,
  "items": [{"id": 101, "lock_version": 3}],
  "allow_regeneration": false
}
```

`allow_regeneration` è sempre richiesto per rendere esplicita la scelta. Si applica alle Expense generate; per Expense non generate non modifica il comportamento.

### Success response

```json
{
  "data": {
    "action": "move",
    "affected_count": 2,
    "destinations": [
      {"origin_expense_id": 101, "destination_expense_id": 201, "planning_year_id": 43},
      {"origin_expense_id": 102, "destination_expense_id": 202, "planning_year_id": 43}
    ]
  }
}
```

`destinations` compare soltanto per Move; per Close/Delete è una lista vuota o omessa coerentemente nei tipi frontend. Il client non invia N richieste single-record in loop.

### Failure behavior

- `409 STALE_VERSION` per almeno un item stale;
- `404 RESOURCE_NOT_FOUND` per ID/Planning Year/relazioni estranee senza indicare quale record appartiene a un altro Tenant;
- `403 PERMISSION_DENIED` se un item non è autorizzato;
- `422 VALIDATION_FAILED` o errore dominio stabile per payload/azione non applicabile;
- nessuna response di successo parziale.

## Contratti frontend

`ExpenseListParams` contiene `planning_year_id`, `kind`, `q`, `cost_center_id`, `project_id`, `contract_id`, `vendor_id`, `state`, `page`, `per_page`; `listExpenses` converte esclusivamente `planning_year_id` in `year`.

`getExpense(expenseId, planningYearId)` passa sempre `year`. I tipi Register/detail includono soltanto campi realmente esposti sopra. Preferenze e bulk usano union/type espliciti; non viene usato `Record<string, unknown>` per contratti noti.
