# Feature 007 — Tenancy and access control

## Status

Clarification in progress. Product decisions Q-001 through Q-015 are approved. Remaining non-blocking and high-priority questions stay recorded in `docs/replatform/product-clarification-register.md`.

## Purpose

Master Plan IT supports multiple customer tenants in one Laravel application. Each tenant represents one customer. Operational data is isolated by tenant and every server-side read, write, report, export, print, attachment access and download must enforce tenant context and role authorization.

## Actors

### Administrator

Global role formed by merging the legacy System Manager and vCIO Manager responsibilities.

- manages all tenants and all tenant users;
- selects a tenant explicitly without impersonating another user;
- retains the Administrator identity in audit records;
- can enter any active tenant context;
- sees the global operational overview;
- performs imports, migration, backup and restore operations;
- manages the lifecycle and configuration of financial years.

### Editor

Tenant-bound role replacing Client Editor.

- belongs to exactly one tenant;
- can create and modify tenant business data;
- can create expenses and Estimate, Quote and Actual rows;
- can replace non-Actual rows;
- cannot alter or delete an Actual already recorded or erase economic history;
- manages plafond, vendors, cost centers, projects, contracts, states, terms and renewals;
- can export, print, create scenarios, manage attachments and read tenant audit;
- cannot manage users, imports, migrations, backups, restores or global operations.

### Viewer

Tenant-bound role replacing Client Viewer.

- belongs to exactly one tenant;
- has full read-only access to the tenant business domain;
- can view dashboard, amounts, detailed expense rows, projects, contracts, vendors, cost centers, plafond, attachments, audit and reports;
- can print and export tenant data;
- cannot modify data, manage users or perform global operations.

## Tenant lifecycle

- Only an Administrator creates, deactivates and reactivates tenants.
- Tenant states are `Active` and `Inactive`.
- Permanent tenant deletion is not supported.
- Deactivation preserves users, business records, attachments and audit history.
- Behaviour while inactive remains tracked as an open clarification until Q-016 is answered.

## Tenant creation

Required fields:

- display name;
- unique code;
- currency;
- language;
- timezone;
- default VAT rate.

Optional fields:

- logo;
- legal and company data;
- address;
- contacts;
- report header and footer.

The application shell retains Master Plan IT branding. Tenant branding applies to customer-facing reports and printed output.

## Tenant context

- The current tenant is always visible in the side navigation and page breadcrumbs.
- Only an Administrator can switch tenant context.
- Editor and Viewer always operate in their assigned tenant.
- Administrator actions inside a tenant remain attributed to the Administrator.
- Direct access to another tenant's resource must be denied server-side without leaking the resource's data.

## Data ownership

Tenant-bound business data includes:

- financial years and their tenant-specific configuration;
- cost centers;
- vendors;
- VAT selections and tenant economic settings;
- categories and templates;
- report settings;
- notifications;
- expenses and expense rows;
- plafond;
- projects;
- contracts and renewals;
- attachments;
- tenant audit events.

Global data is limited to:

- tenant registry;
- user accounts;
- role definitions;
- technical platform configuration;
- system-provided currency, language and timezone lists.

No cross-tenant shared business catalogue is required.

## Global Administrator overview

The global overview contains:

- searchable tenant list;
- tenant state;
- Editor and Viewer counts;
- last activity;
- command to enter tenant context;
- global operational alerts;
- upcoming renewals;
- import and migration errors.

It does not aggregate or compare economic values across tenants.

## Migration

- Every legacy Frappe site represents one customer.
- Only one active legacy site must currently be migrated.
- The migration is a controlled one-time operation into an explicitly selected tenant.
- Manual entry or CSV export/import are both permitted.
- A reusable multi-site migration platform is out of scope.
- Reconciliation and explicit approval are required before the imported tenant is accepted.

## Business invariants

1. A tenant-bound user can never access another tenant's data.
2. UI visibility is not authorization; enforcement is server-side.
3. Tenant context applies to routes, queries, policies, reports, exports, prints, attachments, downloads, scheduled work and administrative commands.
4. Administrator actions never masquerade as tenant-user actions.
5. Actual rows and consolidated economic history remain immutable according to the expense-domain rules.
6. Tenant deactivation never deletes tenant data.
7. Economic reports and exports are scoped to one tenant unless a later approved clarification explicitly changes this rule.

## Out of scope

- tenant-specific databases;
- custom tenant domains or subdomains;
- user membership in multiple tenants;
- tenant self-service user administration;
- tenant deletion;
- impersonation;
- white-label application shell;
- global cross-tenant economic analytics;
- automatic generalized multi-site migration.
