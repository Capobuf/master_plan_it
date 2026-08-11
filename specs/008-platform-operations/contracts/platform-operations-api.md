# API contract — Feature 008 Platform operations

Base path: `/api/v1`. Responses use the repository `data` envelope and standard error envelope.
All browser routes reject bearer tokens and require Sanctum session. Tenant routes require active
user, `TenantContext`, permission-team context and active Tenant, except that the current invariant
allows protected Administrator access to an inactive selected Tenant.

## Tenant settings

### `GET /tenant-settings`

Ability: `tenant-settings.view`.

Response `200`:

```json
{"data":{"tenant_id":1,"name":"Azienda","currency_code":"EUR","timezone":"Europe/Rome","default_vat_rate":"22.00","budget_basis":"net","budget_basis_locked":false,"budget_basis_lock_reason":null,"deletion_reason_required":false,"lock_version":1}}
```

### `PUT /tenant-settings`

Ability: `tenant-settings.update`. The Tenant is resolved exclusively from context.

```json
{"name":"Azienda","timezone":"Europe/Rome","default_vat_rate":"20.00","budget_basis":"net","deletion_reason_required":true,"lock_version":1}
```

Closed payload: `tenant_id`, `currency_code`, quota and all other fields return `422`. Response is
the same projection. Errors: `403` permission/context/inactive; `409 STALE_VERSION` or
`TENANT_BUDGET_BASIS_LOCKED`; `422` validation.

## Tenant users

- `GET /users`, `GET /users/{id}`: `tenant-users.view`.
- `POST /users`, `PUT /users/{id}`, `PUT /users/{id}/roles`,
  `POST /users/{id}/deactivate`, `PUT /users/{id}/password`: `tenant-users.manage`.
- `GET /user-role-options`: `tenant-users.manage`; response contains only Tenant role `id` and
  `name`, paginated, and does not expose abilities.

Existing request/response shapes remain unchanged. Cross-Tenant ids return non-disclosing `404`.

## Tenant roles and catalogue

- `GET /roles`, `GET /roles/{id}`: `tenant-roles.view`.
- `POST /roles`, `PUT /roles/{id}`, `DELETE /roles/{id}`: `tenant-roles.manage`.
- `GET /abilities`: `tenant-roles.manage` and returns only catalogue tenant abilities.

Protected or arbitrary abilities return `422` and no role mutation. A role from another Tenant is
not disclosed.

## Audit

### `GET /audit-events`

Ability: `audit.view`; Tenant derived from context. Query: `page`, `per_page` (1..100), optional
`event_type`, `actor_id`, `from`, `to`. Paginated safe projection.

### `GET /platform/audit-events`

Ability: protected `platform.audit.view-global`; optional `tenant_id` filter plus the same paging
filters. Only global Administrator can access.

Audit export is absent.

## Notifications

### `GET /notifications`

Ability: `notification.view`; paginated notifications for the authenticated user only. No target
user id is accepted. Each item exposes `id`, `type`, safe display data, `read_at`, `created_at` and
optional `delivery_status`/`delivery_error` only when those scalar metadata already exist in the
stored notification data. It never exposes message bodies containing secrets, credentials or file
payload. Empty data is valid; absence of delivery metadata is represented as `null`, not invented.

## Platform settings and retention

### `GET /platform/settings`

Ability: protected `platform.settings.manage`. Returns singleton months and lock version.

### `PUT /platform/settings/audit-retention`

Ability: protected `platform.settings.manage`.

```json
{"audit_retention_months":12,"lock_version":1,"confirmation":"RIDUCI AUDIT A 12 MESI"}
```

`confirmation` is required and exact only when reducing retention. Response includes updated
settings and `deleted_audit_events`. Errors: stale `409`; invalid range/confirmation `422`. No
business, revision or BudgetVersion records are targeted.

## Platform overview

### `GET /platform/overview`

Ability: protected `platform.settings.manage`. Returns Tenant operational rows with state,
active/total user counts, last audit activity and upcoming/current Contract renewal facts already
persisted. An `operational_errors` list is populated only from a current persisted error source; in
the baseline, where no such source exists, it is empty. It never reads application logs, infers
errors, returns cross-Tenant economic amounts or behavioral telemetry.

## Password

Existing `PUT /auth/password` remains. Request requires `current_password`, `password`,
`password_confirmation`; success is `204`. No secret appears in response or audit.

## Common errors

`401` unauthenticated; `403` permission/context/inactive; `404` non-disclosing resource miss; `409`
domain conflict; `422` validation. All error responses keep the repository correlation id contract.
