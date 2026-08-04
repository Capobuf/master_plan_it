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
attachment_revision_manifests.csv
attachment_payload_versions.csv
attachments/
checksums.json
```

Operational revisions are minimized to approved portable business snapshots/batch metadata. No audit event, notification, password/hash/token/session/secret, global platform configuration or other-tenant row/file.

CSV UTF-8 is authoritative; XLSX is not an import format.

## Manifest

Format/schema version, source app/commit, source tenant identity, actor/time, currency/language/timezone, tenant attachment quota and deletion-reason-required setting, file counts/sizes/SHA-256, current/historical attachment checksums, included/excluded datasets, compatibility range and package checksum.

## Export

Every query starts with tenant scope. Stable target IDs and legacy IDs remain separate. Planning years export a year identity and active state; January 1/December 31 are derived, not editable columns. Money is normalized decimal strings; dates ISO. Published BudgetVersion snapshots retain exact immutable content/checksum. Attachment payloads, revision manifests and payload-version references match metadata/checksums; repeated references to unchanged bytes remain shared and each distinct payload version appears once in the package. Expense rows retain structured contract/term deletion provenance when present. Deleted projects/contracts/terms remain terminal evidence and are never imported as active or recoverable records.

Export audit records metadata/count/checksum/result only, never payload.

## Import

Administrator selects existing target tenant before run; target is immutable. The same staging, lineage, collision, dry-run, exclusion, batch apply and reconciliation contract as legacy migration applies.

Role import may create/map tenant roles only from the stable assignable permission catalogue. Protected Administrator/platform permissions are rejected. User password hashes are never portable; imported tenant users require Administrator-set credentials after apply.

Operational revisions cannot overwrite current business state. Published BudgetVersion remains immutable. Source IDs are mapped through identity maps; no PK preservation requirement. Tenant operational settings are compared during dry-run and applied through their owning Actions only after explicit approved resolution; mismatch never silently overwrites the target. A portable zero-byte attachment quota is valid. Attachment payloads are privately finalized only after exact validation and target quota reservation; at zero only already-present payload-version references can be reused.

## Apply/result

Fresh exact dry-run, zero unresolved blockers, exclusions and reinforced confirmation required. Batch commits/progress/failure are explicit; replay is idempotent. Acceptance requires post-apply counts/sums/file/checksum reconciliation.

## Tests

- package contains exactly approved one-tenant data;
- prohibited audit/notification/secret/global/other-tenant data absent;
- exact decimal/date/file round trip;
- role escalation rejected and passwords absent;
- same-lineage replay/collision/quarantine;
- current/revision/BudgetVersion/scenario/generation semantics preserved;
- fixed calendar years, tenant operational settings, source-deletion provenance and exact attachment revision restore preserved;
- failed batch result explicit;
- checksum and reconciliation deterministic.
