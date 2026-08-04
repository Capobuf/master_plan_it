# Feature 006 — Data migration and operations

Status: `PRODUCT CLARIFIED; IMPLEMENTATION READY; NOT CUTOVER READY; IMPLEMENTATION NOT STARTED`
Logical owner: Product Owner with migration/operations approval  
Actors: Administrator and deployment operator  
Dependencies: Features 001, 005, and 007

## Objective

Provide repeatable one-tenant legacy migration, complete tenant data portability, installation-wide backup/restore, shared-hosting deployment, scheduler operations, and visible failure notifications without silent data loss or permanent workers.

## Boundaries

- Installation backup/restore is whole-system disaster recovery.
- Tenant export/import is a separate archive/portability contract.
- The verified legacy migration is one active Frappe site into one selected tenant.
- Audit export is excluded at launch and audit events are not part of tenant portability.
- No selective tenant restore, generalized multi-site migration platform, background worker, or hidden retry subsystem.

## User stories

### US-006-01 — Dry-run and migration apply

Administrator imports a versioned package into one immutable selected tenant, reviews quarantine/reconciliation, and applies only after blockers are resolved or explicitly excluded with approval.

### US-006-02 — Tenant export/import

Administrator exports one complete approved tenant business-data package for archive or portability and may dry-run/import it through controlled staging and collision checks.

### US-006-03 — Installation backup and restore

Administrator/deployment operator creates a full backup and verifies restore in an empty environment before the backup is considered valid.

### US-006-04 — Scheduled operations and failure visibility

One cron runs Laravel scheduler. Failed migration/import, backup, and restore verification create database notifications and optional synchronous email for authorized recipients.

## Acceptance scenarios

### AC-006-01 — Immutable target tenant

Once a migration/import run starts, target tenant cannot change. Every staged, mapped, quarantined, applied, attachment, operational revision, budget version and scenario record is associated with that tenant.

### AC-006-02 — Identity and replay

Identity is unique by tenant, source type/DocType, and legacy ID. Replaying the same manifest/lineage is idempotent. Collision with another lineage, manual record, or user-modified target is quarantined; no merge, rename, or overwrite occurs automatically.

### AC-006-03 — Quarantine and cutover

Invalid, conflicting, or unassignable rows are quarantined with source location and machine-readable error. Dry-run completes and reports them. Cutover/apply approval requires zero unresolved blockers; an explicitly excluded row records reason, actor, and approval.

### AC-006-04 — Reconciliation

Reconciliation compares manifest/file hashes, row and attachment counts, exact net sums by year/cost center/type, source/target identity, and error/exclusion counts. A migration is not accepted without signed approval.

### AC-006-05 — Full installation backup

Backup contains database, attachments, and environment-independent configuration. It is invalid until restored and smoke-tested in an empty environment. Restore is not tenant-selective.

### AC-006-06 — Tenant portability package

One tenant export contains approved tenant data, role/assignment metadata, retained operational revisions, budget versions, scenarios, generation exceptions, notifications when approved by the plan, and attachments. It excludes audit events, passwords/hashes, sessions, tokens, secrets, and global technical configuration including audit retention.

### AC-006-07 — Reinforced confirmation

Migration apply, tenant package import apply, and installation restore require reinforced confirmation and explicit actor/correlation audit.

### AC-006-08 — Scheduled failure notification

Failed import/migration, backup, or restore verification creates a deduplicated database notification. Email is attempted only when configured. Mail failure remains visible and is not silently retried.

### AC-006-09 — Current domain constraints during import

Given staged planning years, attachments and tenant portable settings, dry-run accepts only calendar-year identities whose derived dates are January 1 through December 31, validates attachment payloads against the exact Feature 003 type/size/checksum/parent rules and the target tenant current-plus-historical quota, and reports every source/target setting mismatch without silent normalization or overwrite. Tenant portability preserves attachment revision manifests/payload versions and approved tenant operational settings; legacy migration creates only evidence actually present in the source package.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-006-001 | Migration/import shall consume versioned UTF-8 CSV files plus manifest/checksums. | AC-006-01 |
| FR-006-002 | Imported rows shall retain source type and legacy ID separately from target identity. | AC-006-02 |
| FR-006-003 | Raw records shall be staged before domain insertion. | AC-006-03 |
| FR-006-004 | Dry-run shall validate, transform, quarantine, and reconcile without target-domain writes. | AC-006-03 |
| FR-006-005 | Same manifest/lineage replay shall be idempotent. | AC-006-02 |
| FR-006-006 | Invalid/conflicting/unassignable rows shall be quarantined with stable error and source location. | AC-006-03 |
| FR-006-007 | Reconciliation shall compare counts, hashes, attachments, exact sums, errors, and approved exclusions. | AC-006-04 |
| FR-006-008 | Target tenant shall be selected before run and immutable for the run. | AC-006-01 |
| FR-006-009 | Collision resolution shall never silently merge, rename, or overwrite target data. | AC-006-02 |
| FR-006-010 | Installation backup shall cover database, attachments, and environment-independent configuration. | AC-006-05 |
| FR-006-011 | Backup validity shall require restore verification in an empty environment. | AC-006-05 |
| FR-006-012 | Shared-hosting deployment shall use precompiled assets and one cron entry. | AC-006-08 |
| FR-006-013 | Verified legacy migration shall import one Frappe site into one selected tenant; a generalized multi-site platform is out of scope. | AC-006-01 |
| FR-006-014 | Installation restore shall be whole-system only; selective tenant restore shall not be presented. | AC-006-05 |
| FR-006-015 | Administrator shall export one complete tenant portability package with approved inclusions and exclusions; audit events and global platform settings shall be excluded. | AC-006-06 |
| FR-006-016 | Tenant package import shall use staging, dry-run, immutable target tenant, collision quarantine, reconciliation, and explicit apply approval. | AC-006-06 |
| FR-006-017 | Migration/import apply and restore shall use reinforced confirmation. | AC-006-07 |
| FR-006-018 | Failed migration/import, backup, and restore verification shall create synchronous database notifications and optional email without queue workers. | AC-006-08 |
| FR-006-019 | Import, migration, tenant package import, installation backup/restore, and platform operation permissions shall remain Administrator-only. | AC-006-01, AC-006-05 |
| FR-006-020 | Planning-year import shall accept a unique calendar-year identity and derive immutable January 1/December 31 boundaries. A source row whose supplied boundaries are not that calendar year shall be quarantined or rejected with a stable error and never silently normalized. | AC-006-03, AC-006-09 |
| FR-006-021 | Attachment migration/import shall validate the exact Feature 003 extension/detected-MIME pairs, nonempty payload, 10,485,760-byte maximum, checksum, single approved parent, private path and target-tenant quota before apply. Legacy import shall capture only source-supported current attachment evidence; tenant portability shall preserve every included immutable revision manifest, payload version and shared manifest-to-payload reference exactly, storing each distinct payload version once. | AC-006-03, AC-006-04, AC-006-09 |
| FR-006-022 | Tenant portability shall include the approved tenant attachment quota and deletion-reason-required setting plus structured project/contract/term terminal-deletion and source-deletion provenance. Apply shall use owning Feature 007/004/003 Actions after explicit dry-run resolution, preserve deleted sources only as terminal evidence, and shall never reactivate or restore the same deleted logical identity, silently overwrite a conflicting target setting, or fabricate missing provenance. | AC-006-02, AC-006-06, AC-006-09 |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-MIG-001 | Legacy identity mapping is unique by tenant/source/legacy ID. | DomainConflict | TEST-006-001 |
| INV-MIG-002 | Same manifest/lineage cannot create duplicates. | DomainConflict | TEST-006-002 |
| INV-MIG-003 | Cutover/apply requires zero unresolved blockers and recorded approval. | DomainConflict | TEST-006-003 |
| INV-MIG-004 | Run target tenant is immutable. | DomainConflict | TEST-006-004 |
| INV-MIG-005 | Collision never triggers silent merge, rename, overwrite, or unknown-tenant assignment. | DomainConflict | TEST-006-005 |
| INV-OPS-001 | A backup is not valid until restore verification succeeds. | DomainConflict | TEST-006-006 |
| INV-OPS-002 | Tenant portability import is not selective disaster restore. | DomainConflict | TEST-006-007 |
| INV-OPS-003 | Tenant package never contains audit events, passwords, hashes, sessions, tokens, application secrets, or global platform settings. | DomainConflict | TEST-006-008 |
| INV-OPS-004 | Scheduler failure notification is visible and deduplicated; no silent retry loop. | DomainConflict | TEST-006-009 |
| INV-MIG-006 | Import never creates an editable/non-calendar planning year or silently changes supplied year boundaries. | DomainConflict | TEST-006-010 |
| INV-MIG-007 | Imported attachment metadata, immutable payload versions, shared manifest references, distinct-payload quota usage, source-deletion provenance and terminal project/contract/term identities reconcile exactly; no duplicate or missing payload/provenance is fabricated and no terminal identity becomes active or restorable. | DomainConflict | TEST-006-011 |

## Cutover evidence still open

These are operational evidence, not product ambiguity:

- real production export anomalies and dry-run samples;
- final shared-hosting provider/path capabilities;
- signed inventory of legacy reports requiring exact parity.

## Clarification result

Q-020, Q-021, Q-022, Q-023, Q-024, Q-029, access-recovery dependencies and approved Feature 002–004 domain constraints are closed. Their approved outcomes are propagated through the current Feature 006 plan, tasks, migration, backup, deployment, data model, quickstart, and cross-feature registries. The Constitution 5.0.0 integrated `/speckit.analyze` gate passed, so implementation is ready; cutover remains blocked by the explicit real-evidence gates above.
