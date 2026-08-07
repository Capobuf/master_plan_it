# Bidirectional source traceability

Status: `CURRENT — CONSTITUTION 6.0.0 API-ONLY AMENDMENT PROPAGATED`
Authority: Constitution 6.0.0, PD-API-001/approved clarification records, ADR-036, technical
plans and current task/readiness/execution contracts.

Legacy sources establish verified behavior or migration evidence. Product decisions and Constitution establish target changes. Every row maps evidence → requirement/invariant → owning task → focused test. Task IDs refer to the current Feature 001–007 `tasks.md` files.

## Economic and domain rules

| Rule ID | Rule | Source/evidence | Requirement / invariant | Owning tasks | Focused tests |
|---|---|---|---|---|---|
| R-EXP-001 | Only current non-deleted Expense rows contribute to current totals; the initial current register/detail query reads no attachment, package Version or revision-batch persistence before those later capabilities are delivered | `AGENTS.md`; `financial_engine.py`; ADR-035 vertical slice; C-03/C-05 | FR-003-020; FR-005-012; INV-EXP-001; INV-REV-001 | T003-007, T003-008, T005-003, T005-004 | `CurrentExpenseDatasetTest`, `ExpenseRegisterTest` SQL-boundary assertions, `EconomicDatasetQueryTest` |
| R-EXP-002 | Expense kind/type/confirmation/distribution and database-local nullable combinations are closed at persistence per RES-003-015; cross-record aggregate rules remain Action-owned | `mpit_expense.py::_validate_kind_rules`; RES-003-015 | FR-003-001; FR-003-032; FR-003-050/051; INV-EXP-002 | T003-003, T003-004, T003-010, T003-011, T003-013, T003-014 | `ExpenseSchemaTest`, `CreateExpenseTest`, `UpdateExpenseTest`, `ConfirmActualTest` |
| R-EXP-003 | Estimate, Quote and Actual are independent | Q-037 | FR-003-010–012; FR-005-013; INV-EXP-003; INV-ECO-002 | T003-010–T003-012, T005-001, T005-002 | `CreateExpenseTest`, `EconomicEngineTest` |
| R-ACT-001 | Actual is correctable, versionable, restorable and deletable | Q-006; C-05/C-12 | FR-003-030–035; INV-REV-001–003 | T003-013–T003-018 | `ConfirmActualTest`, `ExpenseRevisionHistoryTest`, `RestoreExpenseRevisionTest`, `DeleteExpenseTest` |
| R-ACT-002 | Generated Actual starts ToConfirm and becomes user-authoritative after edit/confirmation; its confirmed actor is a persisted same-tenant user or the protected tenantless Administrator acting in that selected tenant | Q-035; C-13; RES-003-015 | FR-003-032; FR-003-061; FR-004-025–029; INV-ACT-001; INV-EXP-TEN-001; INV-CON-007–008 | T003-013, T003-014, T004-010, T004-011 | `ConfirmActualTest`, `GeneratedActualOwnershipTest`, `ContractOccurrenceSynchronizationTest` |
| R-AMT-001 | Unit price derives amount through the RES-003-014 twelve-fractional-digit product and six-decimal half-up quantization; otherwise amount is manual | `mpit_expense.py::_sync_row_amounts`; C-02; RES-003-014 | FR-003-040; INV-AMT-001 | T003-001, T003-002, T003-010, T003-011 | `MoneyTest`, `ExpenseCalculationTest` |
| R-VAT-001 | VAT decomposition uses exact half-up decimal arithmetic, enforces the RES-003-014 `DECIMAL(12,6)` rate boundary and reconciles Net + VAT = Gross | `tax.py::validate_strict_vat`; C-02; RES-003-014 | INV-VAT-001 | T003-001, T003-002, T003-010, T003-011 | `VatCalculatorTest`, `ExpenseCalculationTest` |
| R-VAT-002 | A non-zero Expense-row amount selects required row VAT or tenant default before calculation | `tax.py::validate_strict_vat` | FR-003-041 | T003-010, T003-011 | `CreateExpenseTest`, `UpdateExpenseTest`, `ExpenseCalculationTest` |
| R-DATE-001 | Single spend date xor complete period plus distribution has a database-local shape guard; date ordering and aggregate semantics are Action-owned | `mpit_expense.py::_validate_row`; RES-003-015 | FR-003-050; INV-DATE-001 | T003-003, T003-004, T003-010, T003-011 | `ExpenseSchemaTest`, `CreateExpenseTest`, `UpdateExpenseTest`, `ExpenseCalculationTest` |
| R-DIST-001 | All/start/end allocation reconciles exactly | `financial_engine.py::allocate_expense_row_to_months` | FR-003-051; INV-DIST-001 | T003-001, T003-002, T003-010, T003-011 | `MonthlyAllocatorTest`, `ExpenseCalculationTest` |
| R-PLF-001 | Extra and a funded-Plafond ID are locally mutually exclusive at persistence; referenced Plafond kind/year validity remains Action-owned | `mpit_expense.py::_validate_kind_rules`; RES-003-015 | FR-003-013; INV-PLF-001 | T003-003, T003-004, T003-010, T003-011 | `ExpenseSchemaTest`, `CreateExpenseTest`, `UpdateExpenseTest` |
| R-PLF-002 | Plafond is same tenant/year and may cross cost center | `mpit_expense.py::_validate_plafond_reference` | FR-003-014; INV-PLF-002 | T003-010, T003-011 | `ExpenseCalculationTest` |
| R-PLF-003 | Plafond contribution is allocated + overrun without double count | Q-040; verified legacy summary | FR-005-015; INV-PLF-003 | T005-001, T005-002 | `EconomicEngineTest` |
| R-PRJ-001 | Project stage controls Estimate/Quote bucket; the pure engine accepts optional stage context and T005-027 projects it only after Feature 004 exists | `financial_engine.py::_get_project_bucket`; Q-040 | FR-004-010–012; FR-005-014; INV-PRJ-001/003 | T004-004, T004-005, T005-001, T005-002, T005-027 | `ProjectBucketFixtureTest`, `EconomicEngineTest`, `ProjectBucketEconomicDatasetTest` |
| R-PRJ-002 | Actual remains primary despite later project stage | Q-040 | FR-004-012; FR-005-014; INV-PRJ-003/004 | T004-004, T005-001, T005-002, T005-027 | `ProjectBucketFixtureTest`, `EconomicEngineTest`, `ProjectBucketEconomicDatasetTest` |
| R-PRJ-003 | Deferred project has target year and explicit promotion | `tasks.py::promote_deferred_projects` | FR-004-015; INV-PRJ-002 | T004-004–T004-006 | `ProjectLifecycleTest`, `PromoteDeferredProjectsCommandTest` |
| R-CON-001 | Contract terms do not overlap | `mpit_contract.py::_validate_terms_no_overlap` | FR-004-020–023; INV-CON-001 | T004-007–T004-009 | `ContractTermOverlapTest`, `ContractLifecycleTest` |
| R-CON-002 | Generation is source-key unique, suppression-aware and no-overwrite | `contract_expense_sync.py`; C-13; PD-GEN-001 | FR-004-025–033; INV-CON-002–008 | T004-010–T004-018 | synchronization, deletion and occurrence-control tests |
| R-CON-003 | Contracts/projects are context or generators, never separate total sources | legacy summary; C-03 | FR-004-011/035; FR-005-010; INV-CON-004; INV-REP-001 | T004-001, T004-004, T005-001–T005-004 | `ContractProjectSchemaTest`, `EconomicEngineTest`, `EconomicDatasetQueryTest` |
| R-BUD-001 | Current Budget is one rolling tenant/year calculation from current Expense rows; manual no-project data requires no Feature 004 generation | Q-036; PD-BUD-001; ADR-036 | FR-005-001/011; INV-ECO-001; INV-BUD-003 | T003-008, T003-011, T005-001–T005-008 | `EconomicDatasetQueryTest`, `CurrentBudgetParityTest`, API dataset contract tests |
| R-BUD-002 | Deliberate states are immutable named BudgetVersion snapshots | Q-036; C-12 | FR-005-040–047; INV-BUD-001–004 | T005-009–T005-018 | BudgetVersion schema, capture, immutability and comparison tests |
| R-BUD-003 | Manual history may be total-only/partial/full without invented dimensions | Q-034 | FR-005-043–044; INV-BUD-004 | T005-011, T005-012 | `ManualBudgetVersionTest` |
| R-BAS-001 | Tenant Budget basis is Net or Gross; all components remain stored | Q-038 | FR-003-022/042; FR-005-016; INV-BAS-001/002 | T003-007–T003-012, T005-001–T005-007 | Expense calculation, current-dataset and Budget parity tests |
| R-REP-001 | API dataset/resources, dashboard, print and export operations share one dataset/scope and exact decimal components; presentation clients do not recalculate | C-08; Q-019; ADR-036 | FR-005-020–025/070; INV-REP-002–006 | T005-003, T005-004, T005-006, T005-021–T005-023 | API dataset/resource, parity, scope and export contract tests |
| R-API-001 | Laravel is the only backend/business owner; every implemented capability has an operation-oriented `/api/v1` contract with ability, Action/Query, request, resource, errors and tenant scope; no placeholder generic CRUD | Constitution 6.0.0; PD-API-001; ADR-036 | API contract foundation and feature requirements | Feature 001–007 API task overlays; A8 capability reconciliation | API capability matrix and OpenAPI parity tests |
| R-API-002 | Sanctum SPA session uses `/sanctum/csrf-cookie`, `statefulApi()` and `auth:sanctum`; browser uses relative same-origin paths and never sees the private internal origin | Q-026; PD-API-001; ADR-036 | auth/context requirements; private deployment contract | Feature 001 API foundation tasks; Feature 007 context tasks | CSRF/session, auth/context and proxy-origin tests |
| R-API-003 | API errors use stable code, safe localized message, fields and correlation ID; money uses exact decimal strings with currency and Net/VAT/Gross | Constitution 6.0.0; error catalogue; C-02 | response/error/money contracts | Feature 001 API foundation; Features 003/005 resources | error/schema/decimal contract tests |
| R-UI-001 | DEPRECATED: former Laravel TailAdmin/Blade frontend and browser contracts | SUPERSEDED BY PD-API-001/ADR-036 | no new API requirement | historical T001-028/T001-029 only | no current Laravel UI gate |
| R-SCN-001 | Scenarios are explicit non-official tenant datasets | Q-027; C-03/C-08 | FR-005-030; INV-SCN-001 | T005-019, T005-020, T005-016–T005-018 | `ScenarioIsolationTest`, `CompareBudgetSourcesTest` |
| R-YEAR-001 | Planning years are immutable calendar identities with derived January 1/December 31 boundaries and create/deactivate/reactivate only; reads require an active tenant context, new selectors return active rows plus at most the explicit same-tenant current inactive value, and every lifecycle Action rolls back business/audit state together | approved Feature 002 clarification; Constitution 6.0.0 C-05/C-11/C-12; authorization and rollback contracts | FR-002-001/002/010/013/014/018; INV-YEAR-001–004; NFR-001-INT-01 | T002-001–T002-003, T002-007–T002-009, T006-003, T006-005–T006-007 | `PlanningYearTest`, API Resource/schema/authorization tests, `WriteRollbackCoverageTest`, `ImportDomainConstraintTest` |
| R-MD-DEL-001 | CostCenter/Vendor delete is distinct, permissioned and allowed only without prohibited current/historical references; CostCenter also requires no descendants; CostCenter Update/Restore parent changes validate the deepest descendant against the proposed ancestry and submitted non-null parent IDs never normalize missing/foreign/soft-deleted records to root; a successful delete links the exact snapshot created by that delete; all reads require an active tenant context and selectors may add only the explicit same-tenant current inactive value | approved Feature 002 clarification; authorization and persisted-revision contracts | FR-002-003/004/010/012/015–017; INV-CC-001–004; INV-MD-DEL-001; INV-MD-REV-001; INV-MD-TEN-001 | T002-001–T002-003, T002-006, T002-010–T002-015 | cost-center Update/Restore affected-subtree depth and Create/Edit submitted-parent denial; six-state rollback evidence; cost-center/vendor lifecycle, exact delete-snapshot, selector, authorization, Resource navigation and direct-route tests |
| R-MD-REV-001 | Vendor/CostCenter use allowlisted Overtrue v6 SNAPSHOT history with package actor attribution; application revision batches own UUID correlation, the closed lifecycle vocabulary and ordered links from RES-002-011; a root must be a persisted tenant-owned approved-Versionable model; root, batch and Version current keys must equal raw originals and persisted state is reloaded, including soft-deleted versionables; `restored_from_version_id` is required only for restore and must reload a persisted Version of the exact root; only domain restore Actions may consume that source to create a validated new current revision; every domain write has observable rollback evidence plus an exact shared-map entry | C-05/C-12; PD-REV-001; RES-002-004/010/011; authorization and rollback contracts | FR-002-012/017; INV-MD-REV-001; INV-MD-TEN-001; NFR-001-INT-01 | T002-004–T002-006, T002-010–T002-015, T002-017 | `VersioningPackageSmokeTest`, `RevisionBatchSchemaTest`, `RevisionBatchIntegrationTest` root-boundary/restore-source/identity/tamper/soft-delete cases, cost-center/vendor exact delete-snapshot and persisted-restore tests, `WriteRollbackCoverageTest` |
| R-SRCDEL-001 | Project/contract/term deletion is irreversible for the same logical identity and never cascades; contract/term deletion retains generated Expenses, source keys and immutable deletion provenance while stopping generation; tombstones cannot be restored or imported as active | Constitution 6.0.0 C-05/C-09/C-12/C-13; approved Feature 004 clarification | FR-004-037/040–044, FR-006-022; INV-PRJ-004; INV-CON-009/010; INV-DEL-003; INV-MIG-007 | T004-001–T004-013, T006-005–T006-011, T007-021–T007-022 | API resource/authorization, terminal-deletion, restore/import denial, synchronization, portability and generation-history tests |
| R-REP-002 | Economic outputs are uncapped and complete-or-error; absolute timing gates run on verified target hosting while CI gates parity/scope/order/memory/query | approved Feature 005 clarification | FR-005-026/027; NFR-005-PERF-01; INV-REP-007–009 | T005-021–T005-025 | output parity/artifact lifecycle and deterministic performance tests |

## Security, identity, branding and operations rules

| Rule ID | Rule | Source/evidence | Requirement / invariant | Owning tasks | Focused tests |
|---|---|---|---|---|---|
| R-TEN-001 | Protected Administrator plus configurable tenant roles | Q-001–Q-009; C-07 | FR-007-002–008; INV-TEN-001–008 | T001-006, T007-003, T007-008–T007-013 | permission catalogue, role management, ability matrix and IDOR tests |
| R-TEN-002 | Every economic dataset/output contains exactly one tenant | Q-010/Q-019; C-11 | FR-005-022–025; FR-007-016; INV-REP-004–006; INV-TEN-006/007 | T005-003–T005-004, T005-021–T005-023, T007-011–T007-015 | dataset authorization, output scope, report isolation and no-cross-tenant-economics tests |
| R-TEN-003 | Context fails closed and never leaks between requests/commands; actor, context actor, selected Tenant and protected resource identities reject current/raw-original primary-key mismatch before reload or ownership lookup, preserving the permission-team scope and non-disclosing denial; protected Policies independently reject a persisted inactive selected Tenant with `TENANT_INACTIVE` | Q-004/Q-015/Q-016; C-07/C-11; authorization contract | FR-003-030/062; FR-007-004/006–009/012/014; INV-EXP-TEN-001; INV-TEN-001–004 | T007-003, T007-004, T007-012, T001-007, T002-003, T003-006 | context/team/middleware/query tests; `TenantOwnershipPolicyConcernTest`; `MasterDataAuthorizationTest`; `ExpenseAuthorizationTest` |
| R-USR-001 | User deactivation preserves tenant-owned authorship/audit history and reports open assignments for manual reassignment | Q-017; Feature 007 AC-007-08/TEST-007-009; C-05/C-11 | FR-007-015; INV-TEN-009 | T007-008, T007-009 | `TenantUserMembershipTest` deactivation history, tenant ownership and reassignment-needed ID assertions |
| R-AUD-001 | Audit retention is global configurable default 24; lowering is reinforced; pruning cannot delete current business rows, revision identity or named BudgetVersion rows; permission-controlled view exists; no export | Q-020; Feature 007 AC-007-10/TEST-007-010; C-05 | FR-001-020/021/026/031; FR-007-017; FR-007-023; INV-PLT-007; INV-TEN-010 | T001-019–T001-021, T001-025–T001-027 | `AuditRetentionTest` protected-record boundary assertions plus settings, scheduler and audit-view authorization/minimization tests |
| R-AUD-002 | Every ordinary audit write passes through one bounded, sensitive-key rejecting recorder; Actions retain event-specific property validation and attachment/file bytes remain excluded | Feature 001 data model; C-05; Feature 007 NFR-007-PRIV-01 | FR-001-017; INV-PLT-006; NFR-007-PRIV-01 | T001-005, T001-026–T001-027, T007-011 | platform write-contract negative tests, audit-view minimization and final cross-feature privacy matrix; each Action's task remains responsible for its exact event properties |
| R-PWD-001 | No public password recovery; Administrator reset, self-change and emergency interactive reset invalidate every affected database session and rotate the remember token without exposing secrets, while ordinary logout preserves other sessions and that token; every password domain write has observable rollback evidence and an exact shared-map entry | Q-026; Feature 001/007 acceptance; C-05/C-07; RES-001-014/020; rollback contract | FR-001-014–017; FR-007-018; FR-007-019; INV-PLT-006; NFR-001-INT-01 | T001-008, T001-012–T001-016 | no-recovery-route, password administration, self-change and console reset tests; `WriteRollbackCoverageTest` |
| R-BRD-001 | Optional tenant branding is Administrator-managed and appears only in selected-tenant report/output content; shell remains Master Plan IT | FR-007-021; Feature 007 data model/plan; Feature 005 output plan | FR-007-021; INV-TEN-001; INV-TEN-004; INV-TEN-006 | T007-001, T007-002, T007-019, T007-020, T005-021–T005-023 | tenant branding, private logo, branding settings, output branding and print tests |
| R-ATT-001 | Attachments follow exact allowlist/size/single-parent/private authorization; activation backfills a verified empty manifest for every pre-capability data revision and every later revision writes a complete manifest, unchanged bytes reuse one immutable payload version, restore is atomic, permanent Expense deletion purges payloads, and non-negative quota (including zero) counts distinct non-purged versions once | approved Feature 003 clarification; Slice 1 data-only boundary | FR-003-061–069; INV-ATT-001–007; INV-EXP-TEN-001 | T003-005, T003-015–T003-024, T007-021–T007-022 | attachment schema/policy/upload/quota-zero/reuse/lifecycle/manifest-backfill/restore/private-download tests |
| R-SET-001 | Attachment quota and per-tenant deletion-reason requirement are changed only by global Administrator through separate protected abilities; neither change rewrites/deletes prior evidence or payloads | approved Feature 003/004 clarification | FR-007-024–026; INV-TEN-011/012 | T007-001, T007-002, T007-005, T007-021, T007-022, T007-011, T007-018 | operational-settings schema, protected-catalogue/role omission, authorization, page and ability-matrix tests |
| R-NOT-001 | Database-first synchronous notifications, optional email, no worker | Q-023; C-06 | FR-001-006/018; FR-004-038; FR-006-018; INV-PLT-004; INV-OPS-004 | T001-017, T001-018, T004-019, T004-020, T006-014, T001-025 | dedup/mail failure, renewal, operation failure and scheduler tests |
| R-MIG-001 | One legacy site imports into one immutable selected tenant while satisfying fixed-year, attachment, quota and source-evidence constraints; apply is Administrator-only and reinforced | Q-011/Q-022/Q-024/Q-029; C-09; Features 002–004 | FR-001-027/029; FR-006-001–013/017/019–022; FR-007-020; INV-MIG-001–007 | T006-001–T006-009, T007-016, T007-017 | package, domain-constraint dry-run, collision, reconciliation, reinforced apply and tenant ownership tests |
| R-PORT-001 | Tenant portability excludes audit/secrets/global settings and preserves tenant operational settings, structured source-deletion provenance and exact retained attachment revision payloads | Q-021; C-05/C-09; Features 003/004/007 | FR-006-015/016/019–022; INV-MIG-006/007; INV-OPS-002/003 | T006-010, T006-011 | export package, exact round-trip and exclusion tests |
| R-BKP-001 | Installation backup is Created then Verified by empty restore rehearsal; Administrator authority and reinforced restore are approved, while cadence, monitoring threshold and final command names are `DEFERRED — POST-MILESTONE` and absent from the manual Expense-to-current-Budget closure | Q-021/Q-024; Product Owner deferral 2026-08-05 | FR-001-028/030; FR-006-010/011/014/017/019; INV-OPS-001/002 | T001-025, T006-012, T006-013 | Future dependency gate, backup/restore contract, scheduler registration and BackupRun tests; no runtime claim in the selected milestone |
| R-DEP-001 | Production consumes one immutable prebuilt artifact | C-06; development/test contract | FR-001-005; FR-006-012; INV-PLT-003 | T001-022, T001-023, T006-015, T006-016 | workflow, release artifact, preflight and immutable deployment tests |

## Requirement coverage ledger

| Feature | Requirement ranges | Owning task groups | Verification task |
|---|---|---|---|
| 001 | FR-001-001–004 | T001-007–T001-016 | T001-024 |
| 001 | FR-001-005–006; FR-001-009–010; FR-001-012–018 | T001-001–T001-018, T001-022–T001-025 | T001-024 |
| 001 | FR-001-007 | T007-001–T007-003, T005-021–T005-023 | T001-024, T005-025, T007-018 |
| 001 | FR-001-008 | T007-005–T007-006 | T007-018 |
| 001 | FR-001-011 | T007-001–T007-007 | T001-024, T007-018 |
| 001 | FR-001-019 | T007-005–T007-006 | T007-018 |
| 001 | FR-001-020–021; FR-001-026; FR-001-031 | T001-019–T001-021, T001-025–T001-027 | T001-024 |
| 001 | FR-001-022 | T001-028–T001-029; operational UI tasks in Features 003–005 | T001-024, T005-026 |
| 001 | FR-001-023 | T001-012–T001-014, T007-008–T007-010 | T001-024, T007-018 |
| 001 | FR-001-024; FR-001-025 | T001-006, T007-008–T007-010 | T001-024, T007-018 |
| 001 | FR-001-027; FR-001-029 | T006-005–T006-009 | T006-018 |
| 001 | FR-001-028; FR-001-030 | T001-025, T006-012–T006-013 | DEFERRED — POST-MILESTONE; future T001-024/T006-018 after Product Owner operational decision |
| 001 | NFR-001-UX-01; NFR-001-STATE-01 | T001-028–T001-029, T003-009, T003-012, T005-006–T005-008, T005-026 | T005-026 for selected-milestone runtime; T001-024 remains global verification |
| 002 | FR-002-001–002 | T002-007–T002-009 | T002-016 |
| 002 | FR-002-003–005/008–009 | T002-010–T002-012 | T002-016 |
| 002 | FR-002-006–007 | T002-013–T002-015 | T002-016 |
| 002 | FR-002-010–013 | T002-001–T002-017 | T002-016 |
| 002 | FR-002-014–018 | T002-001–T002-003, T002-007–T002-016 | T002-016 |
| 003 | FR-003-001–002; FR-003-010–014; FR-003-040–042; FR-003-050–052; FR-003-063 | T003-001–T003-012 | T003-020 |
| 003 | FR-003-020–022/060 | T003-007–T003-009 | T003-020 |
| 003 | FR-003-030–035 | T003-013–T003-018 | T003-020 |
| 003 | FR-003-061–069 | T003-005, T003-015–T003-024 | T003-020 |
| 004 | FR-004-001/010–015/037 | T004-001–T004-006 | T004-021 |
| 004 | FR-004-020–023/038 | T004-007–T004-009, T004-019–T004-020 | T004-021 |
| 004 | FR-004-025–039 except FR-004-038 | T004-010–T004-018 | T004-021 |
| 004 | FR-004-040–044 | T004-001–T004-013, T007-021–T007-022 | T004-021 |
| 005 | FR-005-001–003; FR-005-010–016; FR-005-050; FR-005-070 | T005-001–T005-008, T005-026–T005-027 | T005-025 |
| 005 | FR-005-020–027/060 and cross-feature FR-007-021 | T005-003–T005-004, T005-021–T005-024, T007-019–T007-020 | T005-025 |
| 005 | FR-005-030 | T005-019–T005-020 | T005-025 |
| 005 | FR-005-040–047 | T005-009–T005-018 | T005-025 |
| 006 | FR-006-001–009/013/017/019 | T006-001–T006-009, T006-017 | T006-018 |
| 006 | FR-006-010–012/014 | T006-012–T006-017 | T006-018 |
| 006 | FR-006-015–016 | T006-010–T006-011 | T006-018 |
| 006 | FR-006-018 | T006-014 | T006-018 |
| 006 | FR-006-020–022 | T006-003, T006-005–T006-011, T007-022 | T006-018 |
| 007 | FR-007-001–015 | T007-001–T007-013, T007-019–T007-020 | T007-018 |
| 007 | FR-007-016/022 | T007-014–T007-015 | T007-018 |
| 007 | FR-007-017/023 | T001-019–T001-021, T001-025–T001-027 | T007-018 |
| 007 | FR-007-018/019 | T001-008, T001-012–T001-016 | T007-018 |
| 007 | FR-007-020 | T007-016–T007-017 | T007-018 |
| 007 | FR-007-021 | T007-001, T007-002, T007-019, T007-020, T005-021–T005-023 | T007-018 |
| 007 | FR-007-024–026 | T007-001, T007-002, T007-005, T007-011, T007-021, T007-022 | T007-018 |

## API-only reconciliation evidence

The implemented A2–A8 API slice is reconciled against the current route and
resource inventory. Pending overlay items remain explicitly unchecked and are
classified as absent or planned in the matrix. The authoritative contract artifacts are:

| Evidence | Covers | Boundary |
|---|---|---|
| `docs/api/v1-capability-matrix.md` | A8-API-001; every current capability classified with ability, Action/Query, request, resource, errors and tenant scope | Does not invent endpoints for persistence-only or planned features |
| `docs/api/openapi-v1.yaml` | A8-API-002; every `IMPLEMENTED_API` `/api/v1` operation plus `/sanctum/csrf-cookie` | Static OpenAPI 3.1 only; no Swagger runtime; A9 parity/removal/final gates remain pending |

The matrix and OpenAPI are reconciled to the route fragments under
`routes/api/v1/`; the browser contract uses Sanctum session/CSRF and relative
same-origin paths, while Laravel remains the sole authorization and economic
owner.

## Coverage gate

No row permits implementation of unstated behavior. A task is executable only when its owning task entry, readiness row, exact command and any required path expansion agree. The historical Constitution 5.0.0 integrated analysis does not authorize the API-only runtime. A later normative change requires another analysis before implementation continues from the changed contract.
