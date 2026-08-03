# Feature 006 — Data migration and operations

Status: `PRODUCT CLARIFIED; NOT CUTOVER READY — PLAN REGENERATION REQUIRED`  
Logical owner: Product Owner with migration/operations approval  
Actors: Administrator and deployment operator  
Dependencies: Features 001, 005, and 007

## Objective

Provide repeatable one-tenant legacy migration, complete tenant data portability, installation-wide backup/restore, shared-hosting deployment, scheduler operations, and visible failure notifications without silent data loss or permanent workers.

## Boundaries

- Installation backup/restore is whole-system disaster recovery.
- Tenant export/import is a separate archive/portability contract.
- The verified legacy migration is one active Frappe site into one selected tenant.
- No selective tenant restore, generalized multi-site migration platform, background worker, or hidden retry subsystem.

## User stories

### US-006-01 — Dry-run and migration apply

Administrator imports a versioned package into one immutable selected tenant, reviews quarantine/reconciliation, and applies only after blockers are resolved or explicitly excluded with approval.

### US-006-02 — Tenant export/import

Administrator exports one complete tenant package for archive or portability and may dry-run/import it through controlled staging and collision checks.

### US-006-03 — Installation backup and restore

Administrator/deployment operator creates a full backup and verifies restore in an empty environment before the backup is considered valid.

### US-006-04 — Scheduled operations and failure visibility

One cron runs Laravel scheduler. Failed migration/import, backup, and restore verification create database notifications and optional synchronous email for authorized recipients.

## Acceptance scenarios

### AC-006-01 — Immutable target tenant

Once a migration/import run starts, target tenant cannot change. Every staged, mapped, quarantined, applied, attachment, revision, and audit record is associated with that tenant.

### AC-006-02 — Identity and replay

Identity is unique by tenant, source type/DocType, and legacy ID. Replaying the same manifest/lineage is idempotent. Collision with another lineage, manual record, or user-modified target is quarantined; no merge, rename, or overwrite occurs automatically.

### AC-006-03 — Quarantine and cutover

Invalid, conflicting, or unassignable rows are quarantined with source location and machine-readable error. Dry-run completes and reports them. Cutover/apply approval requires zero unresolved blockers; an explicitly excluded row records reason, actor, and approval.

### AC-006-04 — Reconciliation

Reconciliation compares manifest/file hashes, row and attachment counts, exact net sums by year/cost center/phase, source/target identity, and error/exclusion counts. A migration is not accepted without signed approval.

### AC-006-05 — Full installation backup

Backup contains database, attachments, and environment-independent configuration. It is invalid until restored and smoke-tested in an empty environment. Restore is not tenant-selective.

### AC-006-06 — Tenant portability package

One tenant export contains approved tenant data, role/assignment metadata, revisions/budget versions/scenarios/audit according to retention, and attachments. It excludes passwords/hashes, sessions, tokens, secrets, and global technical configuration.

### AC-006-07 — Reinforced confirmation

Migration apply, tenant package import apply, and installation restore require reinforced confirmation and explicit actor/correlation audit.

### AC-006-08 — Scheduled failure notification

Failed import/migration, backup, or restore verification creates a deduplicated database notification. Email is attempted only when configured. Mail failure remains visible and is not silently retried.

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
| FR-006-015 | Administrator shall export one complete tenant portability package with the approved exclusions. | AC-006-06 |
| FR-006-016 | Tenant package import shall use staging, dry-run, immutable target tenant, collision quarantine, reconciliation, and explicit apply approval. | AC-006-06 |
| FR-006-017 | Migration/import apply and restore shall use reinforced confirmation. | AC-006-07 |
| FR-006-018 | Failed migration/import, backup, and restore verification shall create synchronous database notifications and optional email without queue workers. | AC-006-08 |
| FR-006-019 | Import, migration, tenant package import, installation backup/restore, and platform operation permissions shall remain Administrator-only. | AC-006-01, AC-006-05 |

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
| INV-OPS-003 | Tenant package never contains passwords, hashes, sessions, tokens, or application secrets. | DomainConflict | TEST-006-008 |
| INV-OPS-004 | Scheduler failure notification is visible and deduplicated; no silent retry loop. | DomainConflict | TEST-006-009 |

## Cutover evidence still open

These are operational evidence, not product ambiguity:

- real production export anomalies and dry-run samples;
- final shared-hosting provider/path capabilities;
- signed inventory of legacy reports requiring exact parity.

## Clarification result

Q-021, Q-022, Q-023, Q-024, Q-029, and access-recovery dependencies are closed. Existing Feature 006 plan, tasks, migration, backup, deployment, data model, and quickstart must be regenerated before implementation/cutover.
