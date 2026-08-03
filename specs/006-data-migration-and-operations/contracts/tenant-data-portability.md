# Contract — Tenant data portability

Feature: `006-data-migration-and-operations`  
Status: `PROPOSED TARGET — PLAN COMPLETE`  
Purpose: export/archive and controlled staged import of one tenant's portable business data.

## Boundary

Not installation backup or selective DR. Administrator exports/imports exactly one tenant. Ordinary report exports and audit access are separate. Audit events, global settings and transient notifications are excluded at launch.

## Package

```text
manifest.json
tenant.csv
users.csv
roles.csv
role_assignments.csv
planning_years.csv
cost_centers.csv
vendors.csv
projects.csv
contracts.csv
contract_terms.csv
contract_generation_exceptions.csv
expenses.csv
expense_rows.csv
attachments.csv
scenarios.csv
scenario_rows.csv
budget_versions.csv
budget_version_rows.csv
operational_revisions.csv
attachments/
checksums.json
```

Operational revisions are minimized to approved portable business snapshots/batch metadata. No audit event, notification, password/hash/token/session/secret, global platform configuration or other-tenant row/file.

CSV UTF-8 is authoritative; XLSX is not an import format.

## Manifest

Format/schema version, source app/commit, source tenant identity, actor/time, currency/language/timezone, file counts/sizes/SHA-256, attachment checksums, included/excluded datasets, compatibility range and package checksum.

## Export

Every query starts with tenant scope. Stable target IDs and legacy IDs remain separate. Money is normalized decimal strings; dates ISO. Published BudgetVersion snapshots retain exact immutable content/checksum. Attachment payloads match metadata/checksums.

Export audit records metadata/count/checksum/result only, never payload.

## Import

Administrator selects existing target tenant before run; target is immutable. The same staging, lineage, collision, dry-run, exclusion, batch apply and reconciliation contract as legacy migration applies.

Role import may create/map tenant roles only from the stable assignable permission catalogue. Protected Administrator/platform permissions are rejected. User password hashes are never portable; imported tenant users require Administrator-set credentials after apply.

Operational revisions cannot overwrite current business state. Published BudgetVersion remains immutable. Source IDs are mapped through identity maps; no PK preservation requirement.

## Apply/result

Fresh exact dry-run, zero unresolved blockers, exclusions and reinforced confirmation required. Batch commits/progress/failure are explicit; replay is idempotent. Acceptance requires post-apply counts/sums/file/checksum reconciliation.

## Tests

- package contains exactly approved one-tenant data;
- prohibited audit/notification/secret/global/other-tenant data absent;
- exact decimal/date/file round trip;
- role escalation rejected and passwords absent;
- same-lineage replay/collision/quarantine;
- current/revision/BudgetVersion/scenario/generation semantics preserved;
- failed batch result explicit;
- checksum and reconciliation deterministic.
