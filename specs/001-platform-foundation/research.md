# Research — Feature 001 Platform foundation

Verification date: 2026-08-03. Primary research is centralized in `docs/replatform/technical-research.md`.

| ID | Decision | Reason | Rejected |
|---|---|---|---|
| RES-001-001 | PHP 8.3.32 + Laravel 13.22.0 | Current supported floor and framework release; Composer platform prevents accidental PHP 8.4+ dependencies. | floating PHP/Laravel or `--ignore-platform-reqs` |
| RES-001-002 | Sail 1.64.0 canonical environment | Official Laravel Docker workflow, same commands for developers/agents/CI. | custom Docker stack without verified need |
| RES-001-003 | MySQL 8.4.10 only | LTS, minimal compatibility matrix, likely hosting support. | speculative MySQL 9.x matrix |
| RES-001-004 | Filament 5.7.3 + Livewire 4.3.3 | Compatible current releases; native components cover shell/forms/tables. | Preline or second UI kit |
| RES-001-005 | Spatie Permission 8.3.0 teams + Shield 4.3.1 | Laravel 13/Filament 5 compatible; tenant-scoped configurable roles. | custom ACL, fixed role branches |
| RES-001-006 | Typed singleton platform settings | Only one approved global value initially; relational typed validation and concurrency. | generic key/value settings package |
| RES-001-007 | Application-owned audit table | Dynamic retention/minimization/no-export requirements are small and explicit. | generic audit-log package |
| RES-001-008 | Persistent separate test DB without reset traits | Approved project safety contract. | RefreshDatabase/DatabaseTruncation |
| RES-001-009 | Database notifications + sync email | One cron, no worker, visible mail failure. | Redis/WebSockets/queued notifications |
| RES-001-010 | Immutable release ZIP after quality gates | Hosting runs verified artifact unchanged. | build dependencies/assets on production host |
| RES-001-011 | Audit retention integer 1–120 months, default 24 | Gives the Administrator a bounded setting while preserving the approved default. | unbounded value or fixed 24 months |
| RES-001-012 | Generic retention-reduction warning without preview | Product selected a warning that discloses neither cutoff nor eligible-event count. | calculated cutoff/count preview |
| RES-001-013 | Ordinary logout revokes only the current session | Avoids unexpected logout on other devices; password change/reset retain their distinct all-session behavior. | ordinary logout revoking every session |
| RES-001-014 | WCAG 2.2 AA and explicit browser/viewport matrix | Provides measurable launch compatibility and accessibility acceptance. | unspecified “responsive/accessibile” claim |

## Executable gates

- Composer exact resolution on platform PHP 8.3.32;
- package migration inspection before publish;
- Spatie team-context request isolation test;
- Shield permission generation diff reviewed and committed;
- no destructive DB reset static guard;
- production artifact content/manifest test.

A failed gate blocks the feature and requires an ADR amendment. No automatic alternative is selected.
