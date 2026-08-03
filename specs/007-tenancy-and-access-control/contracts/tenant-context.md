# Contract — Tenant context

## Representation

- The current tenant name is visible in side navigation and page breadcrumbs.
- Administrator can switch tenant context through an explicit control.
- Editor and Viewer cannot switch context.

## Missing or invalid context

- Tenant-bound routes require a valid current tenant.
- Missing, inactive or unauthorized context must not fall back to another tenant or to unscoped data.
- Error responses must not disclose another tenant's resource existence or content.

## Cross-tenant access

The following must be denied server-side:

- direct links to another tenant's record;
- modified identifiers in forms or requests;
- cross-tenant relationships;
- reports or exports containing another tenant's data;
- attachment and download URLs for another tenant;
- background or scheduled work executed without explicit tenant ownership.

## Administrator behaviour

- Administrator selects a tenant explicitly.
- Administrator keeps their own identity.
- Audit records both Administrator identity and selected tenant.
- The global overview is operational and does not aggregate economic values.

## Session expiry

After session expiry, authorization and tenant context must be re-established. Cached or browser-held identifiers do not authorize access.

## Inactive tenant

The exact read/write behaviour for inactive tenants remains open under Q-016 and must not be invented during implementation.

## Commands and jobs

Administrative commands, imports, migrations, exports and scheduled tasks must receive or resolve explicit tenant ownership and fail closed when it is absent or invalid.
