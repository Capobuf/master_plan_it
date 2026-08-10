# Dashboard API contract delta

Endpoint invariato: `GET /api/v1/dashboard?planning_year_id={id}`.

La risposta conserva l'envelope e tutti i campi correnti. Il payload `data` aggiunge:

```json
{
  "by_project": {
    "Migrazione Microsoft 365": "120000.00",
    "Senza progetto": "25000.00"
  },
  "ancillary": {
    "expenseCounts": {
      "total": 128,
      "open": 97,
      "closed": 31
    },
    "recentExpenses": [
      {
        "id": 123,
        "label": "Rinnovo Microsoft 365",
        "date": "2026-08-10T09:30:00Z",
        "cost_center": "Software",
        "project": "Migrazione Microsoft 365",
        "vendor": "Microsoft Italia S.r.l.",
        "planned": "120000.00",
        "actual": "95000.00",
        "state": "open"
      }
    ]
  }
}
```

Regole:

- `by_project` usa stringhe decimali sulla base ufficiale del Tenant e lo stesso dataset economico corrente; include `Senza progetto`.
- `expenseCounts` considera soltanto Spese correnti e non eliminate del Tenant e Planning Year selezionati; `total = open + closed`.
- `recentExpenses` contiene al massimo otto record ordinati per ultima modifica; `planned` è la pianificazione corrente, `actual` somma gli Actual correnti sulla base ufficiale e nessun valore monetario è un float.
- `ancillary.upcomingContractEvents` conserva la shape corrente con `event_type` uguale a `renewal` o `contract_end`.
- Quando non esiste un Planning Year, `by_project` è `{}` e `expenseCounts` è `{ "total": 0, "open": 0, "closed": 0 }`.
