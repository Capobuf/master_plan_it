# Open evidence and operational decisions

Status: `NO OPEN PRODUCT QUESTIONS`

All product questions Q-001 through Q-033 are closed in `product-clarification-register.md`. This file contains evidence that cannot be invented during specification and technical selections that require a real compatibility spike.

## Blocking for `CUTOVER READY`

| ID | Evidence required | Owner | Why it cannot be guessed |
|---|---|---|---|
| OQ-001 | Production export dry-run manifest with anomaly counts and representative samples | Migration owner | Actual source-data quality and references are unknown until exported. |
| OQ-002 | Final hosting provider capabilities, document root, PHP/MySQL versions, cron, filesystem permissions, dump tools, storage limits, and deployment path | Operations owner | Hosting constraints are environment facts. |
| OQ-004 | Signed inventory of legacy reports requiring exact parity, including filters and output formats | Product Owner | Only the Product Owner can approve report retention/removal. |

## Required technical spikes during `/speckit.plan`

| ID | Decision to verify | Acceptance evidence |
|---|---|---|
| TS-001 | `spatie/laravel-permission` exact version and tenant/team scoping | Composer resolution on Laravel 13/PHP baseline; tenant-role isolation tests; cache/context lifecycle; license/maintenance record. |
| TS-002 | `bezhansalleh/filament-shield` exact version and Filament tenancy integration | Resource/page/custom-permission generation; protected Administrator behavior; tenant-scoped role UI; removal path. |
| TS-003 | `mansoor/filament-versionable` plus `overtrue/laravel-versionable` suitability | Compare/restore/delete tests; aggregate revision batching; tenant scope; soft-deleted model history; package maintenance/license; no domain-query leakage. |
| TS-004 | Installation backup package | Database/file scope, shared-hosting dump tools, restore verification, storage/encryption/retention, Laravel/PHP compatibility. |
| TS-005 | CSV/XLSX package boundary for tenant portability | Streaming/memory behavior, exact decimal/date serialization, import staging compatibility, no queue requirement; CSV remains authoritative. |
| TS-006 | PDF renderer | Laravel/PHP/shared-hosting compatibility behind `ReportPdfRenderer`; printable dataset equality. |

A failed spike rejects the package, not the approved product contract. `/speckit.plan` must choose the smallest native Laravel/Filament alternative and record the technical decision.

## Resolved defaults retained

- dark mode remains out of initial scope;
- CSV is authoritative exchange format; XLSX is optional presentation/import convenience only after acceptance;
- no permanent worker, Redis, WebSockets, or runtime Node requirement;
- no generalized multi-site migration platform.
