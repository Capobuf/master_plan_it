# Research — Feature 004 Contracts and projects

Verification date: 2026-08-03. Shared package research is in `docs/replatform/technical-research.md`.

| ID | Decision | Reason | Rejected |
|---|---|---|---|
| RES-004-001 | Contracts generate Expense rows through Feature 003 Actions | Keeps one current monetary source and shared validation/revisions. | separate contract-cost table or observer |
| RES-004-002 | Canonical immutable source key + unique tenant index | Deterministic idempotency across scheduler/manual actions/import. | title/date matching or random keys |
| RES-004-003 | Separate non-economic generation exception | Suppression controls behavior without becoming economic data. | cancelled Expense row as suppression marker |
| RES-004-004 | System-managed flag ends on manual edit or confirmation | Allows sync before user ownership and prevents silent overwrite. | confirmed rows permanently immutable or always overwriteable |
| RES-004-005 | Project stages classified by shared economic kernel | Avoids project totals and duplicated report formulas. | stored project totals or per-report stage logic |
| RES-004-006 | Native scheduler/database notifications | Meets launch events without worker/Redis. | queued/real-time notification stack |
| RES-004-007 | React/Inertia/Tailwind/TailAdmin term editor and history page | Reuses the shared authenticated tenant/domain stack with the branch's single frontend stack. | Livewire/Blade/Preline, Filament as implicit owner, custom SPA/timeline package |
| RES-004-008 | Non-cascading source deletion with immutable Expense provenance | Product requires generated Expenses and source keys to survive contract/term deletion while future generation stops. | cascade delete or silent detachment |
| RES-004-009 | Per-tenant deletion-reason requirement administered globally | A dedicated protected Administrator ability controls the selected tenant's optional/required prompt without granting delete authority or exposing the setting to tenant roles. | tenant-role switch, one global value or always-required reason |
| RES-004-010 | Terminal tombstones for project/contract/term deletion | Preserves minimized audit/provenance and source-key constraints while enforcing the approved irreversible application behavior for the same logical identity. | recoverable soft-delete lifecycle, cascade hard delete or revision-based restoration of a terminal identity |

## Executable gates

- concurrent sync/manual generation creates one source occurrence;
- restore cannot mutate existing source keys/history or reactivate/restore the same deleted project, contract or term identity;
- mail failure remains visible while DB notification persists;
- stage changes never remove attributed Actual from current primary dataset.
- project deletion locks/rejects current Expense references; contract/term deletion preserves Expenses/source keys and atomically records provenance.
- deletion-reason setting/reason validation is tenant-scoped and prior evidence is immutable.
