# Risk register

| Risk | Probability | Impact | Evidence | Mitigation / verification | Owner | Features |
|---|---|---|---|---|---|---|
| Economic-rule loss | M | Critical | distributed Frappe logic | traceability + equivalence fixtures | Domain lead | 003-005 |
| Double counting | M | Critical | contracts are generators | single ledger query contracts | Domain lead | 004-005 |
| VAT/rounding drift | H | High | current floats vs target decimals | golden fixtures and explicit rounding ADR | Domain lead | 003,006 |
| Replacement-chain loss | M | High | self-links and retained rows | legacy ID mapping + chain reconciliation | Migration lead | 003,006 |
| Legacy reference loss | M | High | Frappe names used as links | immutable legacy identifier columns | Migration lead | 006 |
| Hosting incompatibility | M | High | shared-hosting constraint | deployment proof on representative host | Ops | 001,006 |
| Timeout on reports/migration | M | High | unknown production volume | cardinality survey, indexes, chunking | Ops | 005,006 |
| Semantic report drift | H | High | multiple current adapters | dataset contracts, not DOM-only tests | Reporting lead | 005 |
| Permission mismatch | M | High | Frappe role metadata | action matrix and policy tests | Security lead | 001-006 |
| Livewire overuse | M | Medium | rich expense editor | Blade-first gate and request budget | UI lead | 001,003,005 |
| Preline/Livewire conflicts | M | Medium | DOM morphing | centralized idempotent initializer | UI lead | 001 |
| Excess JavaScript | M | Medium | UI library integration | JS ownership rules | UI lead | 001,005 |
| Incomplete migration | M | Critical | unknown data defects | staged import and reconciliation | Migration lead | 006 |
| Weak rollback | L | Critical | cutover changes | immutable export, backup, rehearsed rollback | Ops | 006 |
