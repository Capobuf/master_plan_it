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
| RES-004-007 | Native Filament term editor/history page | Existing components sufficient. | custom SPA/timeline package |

## Executable gates

- concurrent sync/manual generation creates one source occurrence;
- restore cannot mutate existing source keys/history;
- mail failure remains visible while DB notification persists;
- stage changes never remove attributed Actual from current primary dataset.
