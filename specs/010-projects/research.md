# Research — Feature 010 Projects

Baseline reviewed: `laravel-replatform@7132a39271b31479b9ad477b0ed212bbfc6359a9`.

## Decisions

### Reuse the Contract aggregate architecture selectively

**Decision**: use explicit Actions, focused Queries, Policy, API Resource, optimistic locking,
`Versionable`, revision batches and audit patterns already used by Contract and master data.

**Rationale**: these are the implemented tenant-safe conventions. Project remains a smaller aggregate
without terms, generation, vendor, renewal or monetary fields.

**Alternatives considered**: generic repository/service layers and copying the Contract aggregate were
rejected as unnecessary complexity.

### No new dependency

**Decision**: use Laravel 13, the current versioning package, BCMath, React 19 and TailAdmin React Free
already installed.

**Rationale**: every required behavior is supported by the current stack.

### Project is not an economic source

**Decision**: persist only Project classification data. Expense rows remain the sole current economic
source; no Project amount, Budget, forecast or stored aggregate is introduced.

**Rationale**: this preserves the approved accounting identity and prevents double counting.

### Extend the shared economic kernel

**Decision**: add Project context to `EconomicDatasetQuery`/`EconomicLine` and classify once in
`EconomicEngine`; expose server-calculated Primary, Proposed, Idea, Excluded and Potential.

**Rationale**: Dashboard, Budget and Report already share this path. React only presents the result.

### Preserve Plafond reconciliation

**Decision**: Plafond allocation and uncovered overrun remain Primary contributions; funded consumption
covered by allocation is not counted again in bucket totals.

**Rationale**: the current identity is `allocated + max(consumed - allocated, 0)` and must not regress.

### Reuse RevisionBatch and Versionable

**Decision**: Project writes link version snapshots to logical revision batches. Project-specific query
and resource code expose only safe compare fields. Restore writes a new version and never invokes the
package's direct revert API.

**Rationale**: this is the implemented revision foundation and keeps operational revision separate from
audit and current economics.

### Reuse Tenant deletion policy

**Decision**: trim deletion reason, enforce 500 characters and require it only when
`Tenant.deletion_reason_required` is true. Delete is a terminal soft-delete tombstone.

**Rationale**: this is the current Contract behavior and the approved Feature 008 dependency.

### Operational Deferred promotion

**Decision**: a focused idempotent Action promotes Deferred Project rows whose target year is less than
or equal to the current year in the Tenant timezone. A Laravel command and daily scheduler entry invoke
it using the protected active Administrator as the revision/audit actor; absence of that actor fails
closed.

**Rationale**: revision batches require an actor and the repository has no generic system-actor or
scheduler abstraction. This adds only the wiring needed by the feature.

### React remains presentation-only and TailAdmin-only

**Decision**: React performs routing, loading/error state, ability-based affordance and mutually exclusive
selector UX. Laravel revalidates every invariant and computes every economic result.

**Rationale**: this follows the constitution and current application architecture.

### Local API contracts replace missing global OpenAPI for this slice

**Decision**: maintain feature-local contracts and correct permanent documentation to reflect that the
declared global OpenAPI file is absent. Do not fabricate an incomplete global document.

**Rationale**: `docs/api/openapi-v1.yaml` is absent at the verified HEAD although permanent docs name it.
