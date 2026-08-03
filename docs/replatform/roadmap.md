# Replatform roadmap

1. **001 Platform foundation** — runnable Laravel skeleton, auth, policies, visual system, hosting proof.
2. **002 Master data** — years, cost centers, vendors and settings.
3. **003 Expense domain** — authoritative ledger, decimal money, VAT, replacement and plafond.
4. **004 Contracts and projects** — decision context and generation/synchronization without double counting.
5. **005 Reporting and analytics** — decision dashboard and semantic reports.
6. **006 Data migration and operations** — staged migration, reconciliation, cutover, backup and restore.

## Dependency graph

`001 → 002 → 003 → 004 → 005`  
`001,002,003,004,005 → 006`

Migration tooling may start in parallel after target schemas stabilize, but cutover cannot precede all semantic equivalence gates.
