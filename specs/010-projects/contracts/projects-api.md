# `/api/v1/projects` contract

All routes require Sanctum SPA authentication, active user, tenant context, permission team context,
active Tenant and the route-specific Project ability. Foreign Tenant identifiers are returned as not
found and never disclose record existence.

## Project representation

```json
{
  "id": 41,
  "title": "Rinnovo rete sedi",
  "stage": "deferred",
  "cost_center_id": 12,
  "cost_center": { "id": 12, "name": "Infrastruttura" },
  "deferred_target_planning_year_id": 8,
  "deferred_target_planning_year": { "id": 8, "year_label": 2027, "active": true },
  "expense_count": 2,
  "expenses": [],
  "lock_version": 3,
  "revision_activity": []
}
```

List resources return `expenses: []` and a paginated Laravel resource envelope. Detail includes
current linked Expense summaries (`id`, `title`, `kind`, `planning_year_id`, `planning_year_label`). No
Project monetary amount or total is exposed.

## Operations

| Method | Path | Ability | Input | Success |
|---|---|---|---|---|
| GET | `/api/v1/projects` | `project.view` | `page`, `per_page` | Paginated Project resources |
| POST | `/api/v1/projects` | `project.create` | Project write | `201` Project resource |
| GET | `/api/v1/projects/{project}` | `project.view` | none | Project detail |
| PUT | `/api/v1/projects/{project}` | `project.update` | Project write + `lock_version` | Project resource |
| DELETE | `/api/v1/projects/{project}` | `project.delete` | `lock_version`, nullable `deletion_reason` | `204` |
| GET | `/api/v1/projects/{project}/history` | `project.view-revisions` | pagination | Revision metadata |
| GET | `/api/v1/projects/{project}/history/{revision}` | `project.view-revisions` | none | Safe snapshot/current comparison |
| POST | `/api/v1/projects/{project}/history/{revision}/restore` | `project.restore-revision` | `lock_version` | Restored current Project |

## Write payload

```json
{
  "title": "Rinnovo rete sedi",
  "cost_center_id": 12,
  "stage": "approved",
  "deferred_target_planning_year_id": null
}
```

Update adds a required positive integer `lock_version`. Unsupported fields are validation errors.

Delete input:

```json
{
  "lock_version": 3,
  "deletion_reason": "Piano non più attuale"
}
```

The reason is trimmed, at most 500 characters and required only when the current Tenant setting requires
it. A current linked Expense returns HTTP 409 with code `PROJECT_HAS_LINKED_EXPENSES`. Stale writes return
`STALE_VERSION`. Validation, permission, not-found and conflict responses use the existing error envelope
and correlation ID.

## Revision representations

History item:

```json
{
  "id": 90,
  "operation": "update",
  "actor": "Mario Rossi",
  "timestamp": "2026-08-09T10:00:00+00:00",
  "summary": null,
  "source_revision_id": 301,
  "restored_from_revision_id": null
}
```

Compare returns this metadata plus a whitelisted `snapshot` and `current` containing only title, stage,
Cost Center reference, Deferred target reference and current lock version. The `revision` path parameter is
the logical RevisionBatch id; only the Project version linked to that batch is eligible for restore.
