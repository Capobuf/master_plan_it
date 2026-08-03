# Research — Feature 002 Master data

Verification date: 2026-08-03. Shared package research is in `docs/replatform/technical-research.md`.

| ID | Decision | Reason | Rejected |
|---|---|---|---|
| RES-002-001 | Native Eloquent adjacency list for cost centers | One parent relation and bounded SME trees; no extra package needed. | nested-set/tree package |
| RES-002-002 | Range overlap enforced in Action transaction | MySQL has no portable exclusion constraint for arbitrary date ranges. | trusting form validation only |
| RES-002-003 | Deactivate/reactivate instead of delete | Historical references must remain readable. | cascade deletion or reassignment |
| RES-002-004 | Vendor/cost-center snapshot revisions via shared infrastructure | Product requires compare/restore with one current record. | duplicate current copies or custom per-model history tables |
| RES-002-005 | Planning years use audit/concurrency initially | Product requires controlled configuration but not explicit version restore for years. | speculative year versioning |
| RES-002-006 | Native Filament Resources | Existing components cover tables/forms and active selectors. | Preline or custom CRUD framework |

## Implementation gates

- cycle and overlap transaction tests on MySQL 8.4;
- restore of an inactive/renamed record revalidates uniqueness and references;
- selectors show inactive value only for existing reference, never new selection;
- no cross-tenant parent/reference path.
