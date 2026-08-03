# Open questions

## Blocking for CUTOVER READY

| ID | Question | Required evidence | Owner |
|---|---|---|---|
| OQ-001 | What anomalies exist in the production export? | Dry-run manifest with counts and samples | Migration owner |
| OQ-002 | Which shared-hosting provider/path conventions are final? | Hosting account capabilities and document root | Operations owner |
| OQ-004 | Which legacy reports beyond Economic Position require exact parity? | Signed report inventory and filter list | Product owner |

## Non-blocking for implementation

| ID | Question | Default |
|---|---|---|
| OQ-101 | PDF renderer package | Keep behind `ReportPdfRenderer`; select after Laravel 13/PHP compatibility spike. |
| OQ-102 | XLSX package | Use CSV first; add one package only when XLSX is accepted as a release requirement. |
| OQ-103 | Dark mode | Out of scope for initial release. |

## Resolved product questions

| ID | Decision | Evidence |
|---|---|---|
| OQ-003 | One Laravel application supports multiple customer tenants. Tenant isolation, roles, context, ownership, and migration scope are defined by Q-001 through Q-015 and Feature 007. | `approved-decisions.md`; `specs/007-tenancy-and-access-control/` |
