# Implementation readiness

The original readiness scores predate the approved multi-tenant product change. They are retained as historical depth indicators, not as permission to implement contradictory single-client contracts.

| Feature | Previous minimum | Tenancy convergence | Current status |
|---|---:|---|---|
| 001 Platform foundation | 90 | Roles, tenant context, tenant users, settings and shell contracts updated by Q-001–Q-015; remaining HIGH clarifications still affect inactive-tenant and recovery behavior. | PARTIALLY READY |
| 002 Master data | 91 | Tenant ownership and Editor permissions defined; inactive cost-center/vendor details remain open. | PARTIALLY READY |
| 003 Expense domain | 93 | Tenant ownership and Editor/Viewer permissions defined; attachment retention remains open. | PARTIALLY READY |
| 004 Contracts/projects | 92 | Tenant ownership and Editor powers defined; tenant-scoped generation must be included in implementation plan. | PARTIALLY READY |
| 005 Reporting/analytics | 90 | Tenant-only economic datasets and operational global overview defined; export and empty-state details remain open. | PARTIALLY READY |
| 006 Migration/operations | 89 | One-site-to-one-tenant manual/CSV scope defined; backup/restore scope and real export anomalies remain open. | PARTIALLY READY; NOT CUTOVER READY |
| 007 Tenancy/access control | n/a | Q-001–Q-015 approved and propagated; Q-016–Q-024 remain HIGH. | CLARIFICATION IN PROGRESS |

`IMPLEMENTATION READY` cannot be restored while any `BLOCKING` or `HIGH` product clarification that affects the feature remains open. No coding agent may resolve those questions implicitly.
