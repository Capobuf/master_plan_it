# Open evidence and executable gates

Status: `NO OPEN PRODUCT QUESTIONS; PLAN TECHNICAL DECISIONS RECORDED`

All product questions Q-001–Q-041 are closed.

## Blocking for CUTOVER READY

| ID | Evidence required | Owner | Why it cannot be guessed |
|---|---|---|---|
| OQ-001 | Real production export dry-run manifest with anomaly counts and representative samples | Migration owner | Source quality/references are environment data. |
| OQ-002 | Final hosting provider capabilities, paths, PHP/MySQL, cron, permissions, dump tools, storage/limits and deployment route | Operations owner | Hosting constraints are external facts. |
| OQ-004 | Signed legacy report parity inventory with filters/output | Product Owner | Report retention/removal is product approval. |

## Technical spike outcomes

| ID | Planned outcome | Remaining executable proof |
|---|---|---|
| TS-001 | Spatie Permission 8.3.0 teams, `tenant_id` | Composer lock + team context/cache isolation tests |
| TS-002 | Filament Shield 4.3.1 | Composer lock + protected RoleResource/catalogue smoke |
| TS-003 | Mansoor 5.1 + Overtrue 6.0 snapshot behind application Actions/batches | lock + aggregate compare/restore/delete/tenant smoke |
| TS-004 | Spatie Backup 10.3.0 conditional | PHP8.3.32 Composer resolution, host tools and restore rehearsal; failure blocks/amends |
| TS-005 | CSV authoritative; OpenSpout 4.32 writer-only; no XLSX import | lock + exact decimal/streaming output tests |
| TS-006 | no server PDF package; dedicated Blade print | dataset parity and browser print smoke |

Static research selected the targets; it did not install or execute them. A failed executable proof rejects/amends the technical decision and never activates a silent fallback.

## Retained defaults

- no dark mode requirement at launch;
- no permanent worker, Redis, WebSockets or runtime Node;
- no generalized multi-site migration platform;
- no server-generated PDF file requirement;
- no audit export;
- no automatic backup restore.
