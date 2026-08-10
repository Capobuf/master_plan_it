# Reporting API contract — Feature 019

## Endpoint invariato

`GET /api/v1/reports`

Middleware e authorization restano quelli correnti; è richiesta `report.view`.

## Query

| Parametro | Tipo | Default | Regola |
|---|---|---|---|
| `planning_year_id` | positive integer | — | Planning Year del Tenant corrente; `year` resta alias compatibile |
| `cost_center_id` | positive integer | null | Cost Center del Tenant corrente; `cost_center` resta alias compatibile |
| `project_id` | positive integer | null | Project del Tenant corrente |
| `vendor_id` | positive integer | null | Vendor del Tenant corrente |
| `state` | `open` \| `closed` | null | null significa tutte |
| `group_by` | `cost_center` \| `project` \| `contract` \| `vendor` \| `expense` | `cost_center` | dimensione del dettaglio e delle visualizzazioni |
| `as_of` | datetime string | null | null = corrente; valorizzato = storico |
| `page` | positive integer | 1 | pagina del solo dettaglio |
| `per_page` | integer 1–100 | 25 | dimensione pagina del solo dettaglio |

Cost Center, Project e Vendor inesistenti o appartenenti a un altro Tenant restituiscono il normale `404 RESOURCE_NOT_FOUND`. Uno `state` o `group_by` non ammesso restituisce il normale errore di dominio/validazione senza risultati parziali.

## Response

```json
{
  "data": [
    {
      "key": "cost-center:10",
      "label": "Infrastruttura",
      "group_by": "cost_center",
      "proposed": "1200.00",
      "approved": "1000.00",
      "actual": "850.00",
      "residual": "150.00",
      "variance": "-150.00",
      "utilization_percentage": "85.00",
      "open_expenses": 2,
      "closed_expenses": 1,
      "unapproved_actual_expenses": 0,
      "plafond_expenses": 1
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  },
  "mode": "current",
  "requested_as_of": null,
  "cutoff_utc": null,
  "read_only": false,
  "budget": {
    "planning_year_id": 42,
    "year": 2026,
    "state": "approved",
    "lock_version": 3,
    "warning": null,
    "history_activated_at": "2026-01-01T00:00:00.000000Z"
  },
  "summary": {
    "currency": "EUR",
    "official_basis": "net",
    "proposed": "1200.00",
    "approved_current": "1000.00",
    "actual": "850.00",
    "residual": "150.00",
    "variance": "-150.00",
    "utilization_percentage": "85.00",
    "open_expenses": 2,
    "closed_expenses": 1,
    "unapproved_actual_expenses": 0
  },
  "global_plafond_overrun": "0.00",
  "visualization": {
    "groups": [
      {
        "key": "cost-center:10",
        "label": "Infrastruttura",
        "proposed": "1200.00",
        "approved": "1000.00",
        "actual": "850.00",
        "residual": "150.00",
        "variance": "-150.00",
        "utilization_percentage": "85.00"
      }
    ],
    "proposed_breakdown": [
      {
        "key": "cost-center:10",
        "label": "Infrastruttura",
        "proposed": "1200.00"
      }
    ],
    "expense_states": {
      "open": 2,
      "closed": 1,
      "total": 3
    }
  },
  "filters": {
    "planning_year_id": 42,
    "cost_center_id": null,
    "project_id": null,
    "vendor_id": null,
    "state": null,
    "group_by": "cost_center"
  }
}
```

## Invarianti di calcolo

- Il dataset annuale completo viene riconciliato per il Plafond prima di applicare i filtri Report.
- `summary`, gruppi e `visualization` derivano dallo stesso dataset filtrato e riconciliato.
- `data` è ordinato case-insensitive per label, poi key, e viene paginato server-side.
- `visualization.groups` viene costruito prima della paginazione e contiene al massimo 10 gruppi.
- Il ranking usa la maggiore magnitudine assoluta tra `proposed`, `approved` e `actual`, decrescente; i pareggi usano label case-insensitive e key.
- `proposed_breakdown` usa Proposto decrescente, label e key; conserva i primi cinque gruppi con valore positivo e aggrega gli altri in `{ "key": "other", "label": "Altri" }` tramite BCMath.
- La somma di `proposed_breakdown.proposed` coincide con `summary.proposed`; il frontend usa direttamente il totale del summary al centro del donut.
- `expense_states` usa conteggi e `total = open + closed`.
- `global_plafond_overrun` proviene dal Budget annuale completo e non cambia con i filtri.
- Tutti gli importi e le percentuali restano stringhe decimali; `utilization_percentage` è null quando non definibile.
