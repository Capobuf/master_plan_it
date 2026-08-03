# Global execution plan

| Milestone | Included tasks | Demonstrable result | Gate | Rollback |
|---|---|---|---|---|
| M1 Foundation | T001-01..T001-09 | Login, role policies, shell, settings and shared-hosting smoke | Auth allow/deny tests; compiled assets | Revert feature branch/database migration |
| M2 Master data | T002-01..T002-08 | Years, cost-center tree and vendors usable | overlap/tree/authorization tests | Roll back master-data migrations |
| M3 Expense vertical slice | T003-01..T003-12 | Create Ordinary Actual with VAT and monthly allocation; register view | economic invariant and HTTP tests | Roll back expense schema before real import |
| M4 Planning/replacement/plafond | T003-13..T003-18 | Estimate, Quote, replacement audit, Plafond, Extra | equivalence EQ-001..020 | Feature flag off; no destructive rollback after user data |
| M5 Projects/contracts | T004-01..T004-11 | Project decisions, term timeline, generated Actual rows | sync idempotency/no-overwrite tests | Disable scheduler/sync action; retain created rows |
| M6 Position/reporting | T005-01..T005-11 | Dashboard and Economic Position share one dataset and exports | dataset/export/print equality | Disable navigation; retain queries |
| M7 Migration rehearsal | T006-01..T006-10 | Versioned import dry run and reconciliation report | zero unresolved blocking errors | Drop target DB and repeat |
| M8 Cutover | T006-11..T006-15 | Frozen export imported, verified, backed up and deployed | signed reconciliation and restore test | Restore pre-cutover backup and DNS/app route |

Each milestone is vertical: schema, domain operation, authorization, UI and tests are delivered together. Database-only horizontal construction is prohibited.
