# Data model — Reporting and analytics

## Authoritative persistence

Reporting creates no authoritative economic tables. Query-time datasets read tenant-owned domain records and apply the same server-side calculations used by screens, print, CSV, and XLSX.

## Tenant scope

Every economic dataset requires exactly one tenant. Tenant ID/context is an input to the query contract and is never accepted as an unvalidated user-controlled bypass. No report combines economic values across tenants.

The Administrator global overview uses a distinct operational dataset containing only tenant state, Editor/Viewer counts, last activity, entry action, operational alerts, renewals, and import/migration errors. It contains no cross-tenant economic totals.

## Scenarios

Scenario ownership is tenant-bound. Editor may create scenarios and Viewer may not. Persistence behavior remains governed by the existing scenario specification and any still-open clarification; scenario values never mutate official economic rows.

## Audit

Report reads are not business-state mutations. Export generation records actor, tenant, filters, output type, and correlation ID without logging exported business payloads.
