# Data model — Tenancy and access control

## Tenant

Required semantic attributes:

- stable identifier;
- unique code;
- display name;
- `Active` or `Inactive` state;
- currency;
- language;
- timezone;
- default VAT rate;
- optional logo and company data;
- optional report header and footer;
- creation, deactivation and reactivation audit data;
- optional legacy site identifier.

## User

- global account identity;
- one role: Administrator, Editor or Viewer;
- Editor and Viewer belong to exactly one tenant;
- Administrator is not tenant-bound;
- disabled users retain historical authorship and audit references.

A user-to-tenant pivot is not required by the approved product model.

## Tenant ownership

Every tenant business aggregate must have unambiguous tenant ownership. Child records inherit and validate ownership through their aggregate root. Cross-tenant references are invalid.

## Tenant settings

Tenant-specific settings include financial locale, report branding, configured VAT choices, templates, categories, notifications and report configuration.

## Audit

Audit records must preserve:

- real actor identity;
- actor role;
- tenant context, when applicable;
- action;
- affected resource;
- before/after or equivalent immutable evidence where required;
- timestamp;
- global versus tenant-scoped origin.

Retention and inactive-tenant access remain subject to open clarification Q-020 and Q-016.

## Legacy identifiers

Imported records may preserve stable Frappe identifiers for reconciliation. A legacy identifier never substitutes tenant ownership.
