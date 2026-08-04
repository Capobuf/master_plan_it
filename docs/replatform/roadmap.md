# Replatform roadmap

1. **001 Platform foundation** — runnable Laravel skeleton, authentication, configurable ability-based policy foundation, visual system, and hosting proof.
2. **007 Tenancy and access control** — tenant lifecycle, tenant users, explicit context, ownership, global operational overview, and isolation contracts.
3. **002 Master data** — tenant-owned years, cost centers, vendors, and settings.
4. **003 Expense domain** — tenant-owned authoritative current ledger, decimal money, VAT, immutable revision history, correction/restore/delete operations, and plafond.
5. **004 Contracts and projects** — tenant-owned decision context and generation/synchronization without double counting.
6. **005 Reporting and analytics** — tenant-scoped decision dashboard and semantic reports; Administrator global overview remains operational only.
7. **006 Data migration and operations** — controlled one-site-to-one-tenant migration, reconciliation, cutover, backup, and restore.

## Dependency graph

`001 → 007 → 002 → 003 → 004 → 005`  
`001,007,002,003,004,005 → 006`

Feature 007 is cross-cutting. No tenant-bound table, route, query, report, export, attachment, or scheduled operation is implementation-ready until its tenant ownership and deny-path contract is propagated. Migration tooling may start after target schemas stabilize, but cutover cannot precede isolation and semantic-equivalence gates.
