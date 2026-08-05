# Research — Feature 002 Master data

Verification date: 2026-08-04. Shared package research is in `docs/replatform/technical-research.md`.

| ID | Decision | Reason | Rejected |
|---|---|---|---|
| RES-002-001 | Native Eloquent adjacency list for cost centers | One parent relation and bounded SME trees; no extra package needed. | nested-set/tree package |
| RES-002-002 | Fixed calendar-year identity with derived immutable boundaries | Product removed configurable ranges; tenant/year uniqueness is deterministic and overlap cannot occur. | editable date ranges and overlap locking |
| RES-002-003 | Deactivate/reactivate plus constrained delete for vendors/cost centers | History remains readable while never-referenced records may be removed explicitly. | cascade deletion, reassignment or unconditional hard delete |
| RES-002-004 | Vendor/cost-center snapshot revisions via shared infrastructure | Product requires compare/restore with one current record. | duplicate current copies or custom per-model history tables |
| RES-002-005 | Planning years use audit/concurrency initially | Product requires controlled configuration but not explicit version restore for years. | speculative year versioning |
| RES-002-006 | Native Filament Resources | Existing components cover tables/forms and active selectors. | Preline or custom CRUD framework |
| RES-002-007 | Native adjacency list capped at three levels | Root/child/grandchild is explicit and avoids an unnecessary tree package. | unlimited hierarchy or nested-set package |
| RES-002-008 | Case-insensitive name then ID sibling ordering | Uses existing fields and provides deterministic UI/API/test order without adding a code. | unspecified order or new cost-center code |
| RES-002-009 | Cost centers use a unique supporting key on `(tenant_id, id)` for the restrictive composite self-FK `(tenant_id, parent_id)` | MySQL 8.4.10 runs with `restrict_fk_on_non_standard_key=ON`, so the referenced composite key must be unique. Because `id` is already globally unique, adding tenant ID does not change business identity; it only lets the database enforce same-tenant parents without a trigger. | non-standard referenced index dependent on deprecated MySQL compatibility mode; parent-ID-only FK plus Action-only tenant check; trigger |
| RES-002-010 | Overtrue v6 is integrated through published package schema/config, an application `Version` model that rejects direct `revert()`/`revertWithoutSaving()`, and exact SNAPSHOT field allowlists on Vendor and CostCenter | The locked package supplies snapshot persistence and actor attribution, while application-owned revision batches retain correlation and restore orchestration. A separate integration task makes the measured T002-004 contract executable without assigning package setup implicitly to T002-005. | DIFF strategy; package-owned correlation or restore workflow; technical/tenant/lock fields in snapshots; implicit setup hidden inside the revision-batch task |
| RES-002-011 | Revision batches use a closed `create|update|deactivate|reactivate|restore|delete` operation enum, UUID correlation, unsigned-BIGINT identities/morph IDs, unsigned item sequence, unique batch/version and batch/sequence pairs, and a tenant/root/occurred-at history index; Eloquent relations bind the exact tenant, actor, source batch/source Version, item batch/item Version and morph columns | Vendor and cost-center deactivation/reactivation are distinct permission-controlled lifecycle operations and must remain distinguishable in history. The closed database enum prevents drift outside the application enum; exact types and keys follow the shared relational conventions, preserve deterministic item order and support the application-owned history query without making the batch an event store. | treating deactivate/reactivate as an undocumented update; open operation string; signed or 32-bit identities; unordered or duplicate batch items; package-owned correlation |

## Implementation gates

- fixed-calendar uniqueness, immutable-boundary, cycle and depth transaction tests on MySQL 8.4;
- restore of an inactive/renamed record revalidates uniqueness and references;
- selectors show inactive value only for existing reference, never new selection;
- no cross-tenant parent/reference path.
- vendor/cost-center deletion rejects every current/historical reference and cost-center descendants.
