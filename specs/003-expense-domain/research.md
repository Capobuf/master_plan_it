# Research — Feature 003 Expense domain

Verification date: 2026-08-04. Package research is centralized in `docs/replatform/technical-research.md`.

| ID | Decision | Reason | Rejected |
|---|---|---|---|
| RES-003-001 | BCMath application value objects | Exact decimal arithmetic without float or additional money package. | floats, database-only calculation |
| RES-003-002 | Persist inputs at 6 decimals and business outputs at 2 | Matches allocation precision and reporting boundaries. | storing only one amount or arbitrary JSON |
| RES-003-003 | Independent Estimate/Quote/Actual | Product decision Q-037; no unsupported workflow. | phase state machine |
| RES-003-004 | One current row plus snapshot revisions | Product/Constitution C-05/C-12. | replacement graph/current duplicates |
| RES-003-005 | Overtrue 6.0 snapshot + Mansoor UI through application Actions | Compatible target; snapshot is safer than plugin DIFF strategy. | direct package restore or custom version framework |
| RES-003-006 | Soft delete as infrastructure | Supports active-domain deletion and controlled restore/history access. | hard delete or state enum as deletion |
| RES-003-007 | Full aggregate save with explicit deletions | Preserves transactional editor UX without accidental missing-row deletion. | one request per field/row or implicit diff delete |
| RES-003-008 | No deadlock retry initially | Retrying non-idempotent aggregate writes can hide conflicts. | generic three-retry helper |
| RES-003-009 | Application-owned attachment membership, complete revision manifest and private payload-version storage | Exact aggregate restore requires complete file sets while C-05 forbids payload bytes in audit/revision metadata. | Media Library, inline blobs or metadata-only restore |
| RES-003-010 | Server MIME/extension allow-list and 10 MiB per file | Product-approved PDF/JPEG/PNG/CSV/XLSX boundary is deterministic and testable. | client-only validation or arbitrary file types |
| RES-003-011 | Per-tenant 2 GiB distinct-payload quota with locked reservation | Bounds current plus historical immutable-payload storage and prevents concurrent overrun. | filesystem-capacity-only enforcement |
| RES-003-012 | Complete manifests reuse immutable payload versions for unchanged attachments | Preserves exact revision restore without duplicating bytes; quota measures distinct stored payloads, so data-only revisions remain possible above quota. | one physical copy per manifest, metadata-only history or content reconstruction |
| RES-003-013 | Constitution C-02 owns root readonly `App\Domain\Money\Money` and `VatBreakdown` value objects; Money accepts three ASCII-letter currency input and normalizes it uppercase, and primitive tests own decimal normalization/range/currency, arithmetic/VAT decomposition and allocation, while Expense aggregate validation owns spend-date XOR, type-specific negativity, VAT/default selection and Budget-basis behavior. `MonthlyAllocator` returns ordered `Y-m` keys for every month selected by `all`, but only the selected month for `start` or `end` | This resolves the downstream namespace conflict without amending the Constitution, gives the immutable DTO requirement an executable PHP 8.3 boundary, makes Money compatible with tenant currency input already accepted case-insensitively by RES-007-017, assigns each requirement to an API that can enforce it, and uses the smallest internal mapping that expresses EQ-012–EQ-014 without inventing zero-valued start/end rows. | `Data\Money` namespace; case-sensitive rejection of a valid tenant currency; mutable DTOs; forcing aggregate/date/type/basis rules into generic Money DTOs; dense zero-filled start/end mappings; undocumented output keys |
| RES-003-014 | Money enforces total precision 19 with `19 - scale` integer digits, so scale-six inputs/intermediates and scale-two business results use their full `DECIMAL(19,6)` and `DECIMAL(19,2)` capacities; VAT rates enforce `DECIMAL(12,6)`; quantity/unit-price multiplication computes the full twelve-fractional-digit BCMath product and then quantizes half-up to six decimals before range validation | C-02 defines two distinct persisted capacities, so a fixed thirteen-integer-digit guard rejects valid two-decimal results. PHP 8.3 `bcmul()` returns only the requested scale and therefore truncates if called directly at scale six, while native `bcround()` is unavailable until PHP 8.4; an explicit string/BCMath half-up boundary preserves high precision without floats or a runtime upgrade. The tenant schema fixes VAT rate at precision 12, scale 6, so accepting a seventh integer digit would defer a known invalid value to persistence. | fixed thirteen-digit guard for every scale; `bcmul(..., 6)` silent truncation; float `round()`; PHP 8.4-only `bcround()`; unbounded VAT rate |

## Executable gates

- package snapshot/actor/batch smoke;
- restore through owning Action with exact data/attachment set; a permanently deleted aggregate remains non-restorable;
- exact allocation/accounting fixture parity;
- MySQL source-key and tenant constraint tests;
- no float/accessor/observer formula architecture test.
- attachment checksum/MIME/size, same-tenant parent, quota concurrency, rollback/orphan and permanent-purge tests.
