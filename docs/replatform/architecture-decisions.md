# Architecture decisions

| ID | Decision | Status | Rationale |
|---|---|---|---|
| ADR-001 | Modular Laravel monolith | APPROVED | Shared-hosting compatibility and minimal operations. |
| ADR-002 | Expense rows are sole economic source | APPROVED | Preserves verified legacy semantics and prevents double counting. |
| ADR-003 | `DECIMAL(19,6)` intermediate, 2-decimal business result | APPROVED | Supports monthly allocations and deterministic residual handling. |
| ADR-004 | Money arithmetic via BCMath value objects | APPROVED | Avoids float drift without external package. |
| ADR-005 | Blade by default, Livewire selectively | APPROVED | Server-rendered UI with focused interactivity. |
| ADR-006 | Chart.js only | APPROVED | One maintained chart integration and printable dataset reuse. |
| ADR-007 | No permanent worker; synchronous bounded commands | APPROVED | Shared hosting constraint. |
| ADR-008 | Contract sync is append-missing, never overwrite-existing | APPROVED | Existing generated rows become user-authoritative. |
| ADR-009 | Migration through versioned exchange files and staging | APPROVED | No direct Frappe DB assumption. |
| ADR-010 | Dusk only for JS lifecycle, focus, responsive and print smoke | APPROVED | Avoids duplicating feature tests in a slow suite. |
| ADR-011 | Explicit tenant ownership in one Laravel application | APPROVED DIRECTION | Product requires multi-tenancy and minimum complexity; final implementation must prove isolation before coding. |
| ADR-012 | Administrator tenant context without impersonation | APPROVED | Preserves real actor identity and simplifies audit. |
| ADR-013 | Controlled one-site migration into one selected tenant | APPROVED | Matches the only verified migration case and avoids an unused multi-site platform. |
