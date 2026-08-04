# Research — Feature 006 Migration and operations

Verification date: 2026-08-03. Shared package research is in `docs/replatform/technical-research.md`.

| ID | Decision | Reason | Rejected |
|---|---|---|---|
| RES-006-001 | UTF-8 CSV + JSON manifest/checksums authoritative | Inspectable, streamable, PHP-native and deterministic. | direct Frappe DB, XLSX authoritative import |
| RES-006-002 | Dataset-specific staged importers | Dependencies/invariants differ and must call owning Actions. | generic reflection/ORM importer |
| RES-006-003 | Immutable target + lineage identity map | Repeatability and no cross-tenant/collision ambiguity. | name matching, silent merge/rename |
| RES-006-004 | Batch apply with explicit progress/failure | Bounded shared-hosting execution; honest partial result. | one unbounded transaction or silent continuation |
| RES-006-005 | Whole-installation backup only | Approved DR boundary. | selective tenant restore |
| RES-006-006 | Spatie Backup 10.3 conditional on PHP8.3 Composer gate | Metadata supports target but docs conflict; executable proof required. | automatic downgrade/custom fallback |
| RES-006-007 | Operator-led restore verification | Restore safety cannot be represented by archive creation alone. | one-click web restore |
| RES-006-008 | Immutable CI release artifact | Production consumes tested code/assets unchanged. | build on host |
| RES-006-009 | Exclude audit and transient secrets/settings from portability | Q-020 and privacy/minimization. | complete database dump as tenant package |
| RES-006-010 | Domain-owned import validation for calendar years and attachments | Import must satisfy the same approved invariants as interactive Actions and cannot normalize invalid boundaries or bypass payload quota. | importer-specific weaker validation or direct inserts |
| RES-006-011 | Portable tenant settings and exact attachment revision payloads | The two approved tenant settings and every retained file manifest/payload are required to preserve behavior and revision restore in a round trip. | global-settings export, current-files-only package or fabricated history |
| RES-006-012 | Terminal source identities remain evidence-only across import | Project/contract/term deletion is irreversible for the same logical identity; portability must preserve provenance and source-key integrity without creating an active or recoverable source. | importing tombstones as active rows, revision-restorable sources or new identities |

## Executable gates

- Composer resolution for backup package on platform PHP 8.3.32;
- real `mysqldump`/ZipArchive/storage preflight;
- import package round trip with exact decimals/checksums;
- dry-run leaves current tables unchanged;
- restore rehearsal in empty disposable environment;
- production artifact contains required and excludes prohibited files.
- non-calendar year rows and invalid/over-quota attachment operations remain blockers with no current writes;
- portability round trip preserves tenant settings, structured source-deletion provenance, terminal project/contract/term identity and exact attachment revision manifests/payload checksums without making a terminal identity active or restorable.
