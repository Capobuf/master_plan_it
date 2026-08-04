# Risk register

| Risk | Probability | Impact | Evidence | Mitigation / verification | Owner | Features |
|---|---|---|---|---|---|---|
| Economic-rule loss | M | Critical | distributed Frappe logic and amended target lifecycle | traceability, exact accounting fixtures, plan regeneration | Domain lead | 003-005 |
| Double counting | M | Critical | contracts are generators; versions/scenarios add datasets | current Expense-only queries; dataset-type isolation tests | Domain lead | 003-005 |
| VAT/rounding drift | H | High | current floats vs target decimals | golden fixtures, BCMath Money, explicit rounding | Domain lead | 003,005,006 |
| Legacy replacement-chain loss | M | High | target no longer uses current replacement graph | deterministic current-row selection; migrate old chain into traceable revision/audit evidence; exact reconciliation | Migration lead | 003,006 |
| Revision storage leaks into current totals | M | Critical | version package stores model snapshots/diffs | hard query boundaries, architecture tests, current-dataset equality | Domain lead | 002-005 |
| Aggregate restore creates invalid state | M | Critical | package may restore one model without rows/relations | owning aggregate Restore Action, revision batch, current invariant/tenant validation, rollback tests | Domain lead | 003-004 |
| Deleted Actual remains visible/economic | M | Critical | soft delete/version storage may be queried accidentally | global scopes plus explicit query tests; report/export equality; tombstone minimization | Domain lead | 003-005 |
| Published budget version mutates | L | Critical | generic model editing/version restore could reach snapshot | immutable Published policy/Action; no version-plugin restore path; checksum tests | Reporting lead | 005 |
| Budget comparison misaligns rows | M | High | current and snapshot dimensions may differ | stable comparison keys, explicit added/removed rows, exact fixtures | Reporting lead | 005 |
| Generated expense reappears unexpectedly | M | High | append-missing sync after intentional deletion | explicit delete choice, source-key exception, visible state, suppression tests | Domain lead | 004 |
| Generated expense never resumes | L | High | suppression exception could become orphaned | contract history UI, resume actions, consistency tests | Domain lead | 004 |
| Permission escalation through tenant RBAC | M | Critical | configurable roles and package-generated abilities | protected permission catalogue, Administrator boundary, tenant/team tests, deny-by-default | Security lead | 001,007 |
| Permission context/cache leakage between tenants | M | Critical | RBAC package teams/cache lifecycle | compatibility spike, explicit tenant context reset, cross-request tests | Security lead | 001,007 |
| Package maintenance or compatibility failure | M | High | community versioning/Filament plugins | exact-version spike, license/maintenance evidence, removal path, no domain coupling | Technical lead | 001-007 |
| Password exposure | L | Critical | Administrator-set passwords and exports/audit | hidden inputs, hashing, no logging/export/revision, session invalidation tests | Security lead | 001,007 |
| Cross-tenant data exposure | M | Critical | all business/version/export operations tenant-scoped | explicit ownership; policy/query/report/export/revision/attachment deny tests | Security lead | 001-007 |
| Wrong tenant context | M | High | Administrator operates across tenants | visible context, explicit switch, actor+tenant audit, fail closed | Product/UI lead | 001,007 |
| Tenant export leaks secrets or other tenants | M | Critical | complete portability package | explicit exclusions, file/row scoping, package inspection tests, checksums | Security/Migration lead | 006 |
| Tenant import overwrites current data | M | Critical | identity collisions and multiple lineages | staging, immutable target, collision quarantine, no silent resolution | Migration lead | 006 |
| Unassignable rows silently disappear | M | Critical | source defects | quarantine, apply blocker, approved exclusion with reconciliation | Migration lead | 006 |
| Backup reported valid without restore proof | M | Critical | package backup success does not prove recoverability | Created vs Verified states, empty-environment restore test | Ops | 006 |
| Notification noise or duplicates | M | Medium | cron thresholds repeat daily | stable dedup key, explicit event scope, recipient permissions | Product/Ops | 001,004,006 |
| Email failure hidden | M | Medium | synchronous optional mail | database notification remains authoritative; visible delivery failure; no silent retry | Ops | 001,006 |
| Hosting incompatibility | M | High | shared-hosting constraints and dump tools unknown | representative-host spike and deployment proof | Ops | 001,006 |
| Timeout on reports/migration/export | M | High | unknown production volume and complete packages | cardinality survey, indexes, streaming/chunking without semantic drift | Ops | 005,006 |
| Semantic report drift | H | High | multiple output scopes and dataset types | one dataset contract per selected type/scope, parity fixtures, no DOM-only tests | Reporting lead | 005 |
| Audit removed earlier than expected | M | High | Administrator may lower the global retention period | reinforced confirmation, preview cutoff/count, explicit bounded retention command, audit of setting change | Security/Ops | 001,007 |
| Livewire/plugin UI conflict | M | Medium | Filament plugins and DOM lifecycle | native Filament integration, small Dusk coverage, no custom JS ownership overlap | UI lead | 001-005 |
| Incomplete decision propagation | H | High | Constitution 5.0.0 and approved cross-feature clarifications supersede earlier plans/tasks | explicit stale-artifact list; `/speckit.plan`, `/speckit.tasks`, `/speckit.analyze` gates | Spec owner | 001-007 |
| Cutover evidence unavailable | M | High | real export, hosting, report inventory not yet supplied | keep Feature 006 not CUTOVER READY; no guessed evidence | Product/Ops/Migration | 005-006 |
