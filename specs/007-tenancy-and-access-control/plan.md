# Plan — Tenancy and access control

## Objective

Specify and later implement the minimum Laravel-native tenancy model that satisfies Feature 007 without introducing package-driven or infrastructure-driven complexity.

## Product dependencies

Implementation planning must not begin until all `BLOCKING` and `HIGH` product clarifications in the register are closed and propagated.

## Technical direction

The implementation agent must verify current official Laravel and database documentation before coding. The default direction is:

- one Laravel monolith;
- one relational database;
- explicit `tenant_id` ownership on tenant-bound aggregate roots;
- Laravel authentication, middleware, policies and scoped queries;
- explicit current-tenant context;
- database constraints and indexes supporting isolation;
- no tenancy package unless a verified requirement cannot be met more simply;
- no Redis, distributed queue, microservices, CQRS or event sourcing unless independently justified by an approved requirement.

## Workstreams

1. Tenant and user-role model.
2. Tenant-context resolution and visible context contract.
3. Authorization matrix and cross-tenant denial.
4. Tenant ownership propagation to Features 002–006.
5. Administrator global overview.
6. Tenant settings and report branding.
7. Legacy single-site migration and reconciliation.
8. Isolation, authorization and regression tests.
9. Documentation and traceability convergence.
