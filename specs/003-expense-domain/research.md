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
| RES-003-009 | Native Filesystem attachment table | Small explicit lifecycle and tenant requirements. | Media Library |

## Executable gates

- package snapshot/actor/batch smoke;
- restore through owning Action, including deleted aggregate;
- exact allocation/accounting fixture parity;
- MySQL source-key and tenant constraint tests;
- no float/accessor/observer formula architecture test.
