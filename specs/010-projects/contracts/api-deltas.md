# Expense and reporting API deltas

## Expense write/read

Expense create/update accepts nullable positive integer `project_id`. The aggregate rejects:

- a foreign-Tenant or terminally deleted Project;
- both `project_id` and `contract_id`;
- assigning Project to a Contract-generated Expense that remains source-governed.

Expense detail adds:

```json
{
  "project_id": 41,
  "project_title": "Rinnovo rete sedi"
}
```

Expense register rows add `project_id`, `project_title`, and `project_current`. Existing Contract fields
remain unchanged. Project and Contract values are identifiers/presentation context only.

## Shared reporting summary

Dashboard and Budget dataset summaries, and Report metadata summaries, add exact decimal strings:

```json
{
  "amounts": {
    "primary": "1250.00",
    "proposed": "200.00",
    "idea": "100.00",
    "excluded": "50.00",
    "potential": "1550.00"
  }
}
```

`official_current_position` remains available for compatibility and equals the authoritative Primary
position after Plafond reconciliation. Potential is explicitly non-official.

Report lines add:

```json
{
  "project_id": 41,
  "project_stage": "proposed",
  "bucket": "proposed"
}
```

The server computes `bucket` and Potential. Frontend consumers must not derive them.
