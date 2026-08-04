# Research — Feature 003 Expense domain

Verification date: 2026-08-03. Package research is centralized in `docs/replatform/technical-research.md`.

| ID | Decision | Reason | Rejected |
|---|---|---|---|
| RES-003-001 | BCMath application value objects | Exact decimal arithmetic without float or additional money package. | floats, database-only calculation |
| RES-003-002 | Persist inputs at 6 decimals and business outputs at 2 | Matches allocation precision and reporting boundaries. | storing only one amount or arbitrary JSON |
| RES-003-003 | Independent Estimate/Quote/Actual | Product decision Q-037; no unsupported workflow. | phase state machine |
| RES-003-004 | One current row plus snapshot revisions | Product/Constitution C-05/C-12. | replacement graph/current duplicates |
| RES-003-005 | Overtrue 6.0 snapshot + Mansoor UI through application Actions | Compatible target; snapshot is safer than plugin DIFF strategy. | direct package restore or custom version framework |
| RES-003-006 | Soft delete as infrastructure | Supports active-domain deletion and controlled restore/history access. | hard delete or state enum as deletion |
| RES-003-007 | Full aggregate save with explicit deletions | Preserves transactional editor UX without accidental missing-row deletion. | one request per field/row or implicit diff delete |
| RES-003-008 | No deadlock retry initially | Retrying non-idempotent aggregate writes can hide conflicts. | generic three-retry helper |
| RES-003-009 | Application-owned attachment membership, complete revision manifest and private payload-version storage | Exact aggregate restore requires complete file sets while C-05 forbids payload bytes in audit/revision metadata. | Media Library, inline blobs or metadata-only restore |
| RES-003-010 | Server MIME/extension allow-list and 10 MiB per file | Product-approved PDF/JPEG/PNG/CSV/XLSX boundary is deterministic and testable. | client-only validation or arbitrary file types |
| RES-003-011 | Per-tenant 2 GiB distinct-payload quota with locked reservation | Bounds current plus historical immutable-payload storage and prevents concurrent overrun. | filesystem-capacity-only enforcement |
| RES-003-012 | Complete manifests reuse immutable payload versions for unchanged attachments | Preserves exact revision restore without duplicating bytes; quota measures distinct stored payloads, so data-only revisions remain possible above quota. | one physical copy per manifest, metadata-only history or content reconstruction |

## Executable gates

- package snapshot/actor/batch smoke;
- restore through owning Action with exact data/attachment set; a permanently deleted aggregate remains non-restorable;
- exact allocation/accounting fixture parity;
- MySQL source-key and tenant constraint tests;
- no float/accessor/observer formula architecture test.
- attachment checksum/MIME/size, same-tenant parent, quota concurrency, rollback/orphan and permanent-purge tests.
