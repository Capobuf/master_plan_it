# Research — Feature 005 Reporting and analytics

Verification date: 2026-08-03. Shared package research is in `docs/replatform/technical-research.md`.

| ID | Decision | Reason | Rejected |
|---|---|---|---|
| RES-005-001 | One query + one pure economic engine + four DTOs | Single formula source without a query/format God Object. | calculator per KPI, report-specific engines |
| RES-005-002 | Current Budget is query-time with one context row | No duplicated synchronized current total. | materialized current totals/documents |
| RES-005-003 | BudgetVersion is application snapshot | Immutable multi-row economic baseline differs from model revision. | version package as BudgetVersion |
| RES-005-004 | Scenarios are explicit alternative datasets | Keeps non-official values outside current totals. | overlaying scenario rows on current query silently |
| RES-005-005 | Native streamed CSV + OpenSpout 4.32 writer-only | PHP 8.3 compatible, bounded memory; CSV authoritative. | OpenSpout 5, XLSX import, Laravel Excel |
| RES-005-006 | Dedicated Blade print, no server PDF package | Product requires printing; avoids browser/container/Python/cloud dependency. | Browsershot/Gotenberg/WeasyPrint/DomPDF at launch |
| RES-005-007 | Chart.js only | One chart lifecycle adapter and server-calculated values. | multiple chart libraries |
| RES-005-008 | MySQL REPEATABLE READ capture | Consistent snapshot without global row locking. | copying rows across independent queries/transactions |
| RES-005-009 | No persistent calculation cache initially | 10k-row profile should be measured first; cache invalidation adds risk. | Redis/materialized totals/preaggregation |

## Executable gates

- current query/engine benchmark and EXPLAIN at 10,000 rows;
- snapshot consistency under concurrent Expense update;
- screen/print/CSV/XLSX/version parity;
- OpenSpout exact lock on PHP 8.3.32;
- print view contains no independent formula;
- no operational revision/audit/deleted/scenario/version row in current query.
