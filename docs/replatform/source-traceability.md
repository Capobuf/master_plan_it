# Bidirectional source traceability

Status: `CURRENT AFTER INVARIANT TASK-OWNERSHIP REMEDIATION — FINAL RE-ANALYSIS REQUIRED`
Authority: Constitution 3.0.1, Q-001–Q-041, approved technical plans and current task/readiness/execution contracts.

Legacy sources establish verified behavior or migration evidence. Product decisions and Constitution establish target changes. Every row maps evidence → requirement/invariant → owning task → focused test. Task IDs refer to the current Feature 001–007 `tasks.md` files.

## Economic and domain rules

| Rule ID | Rule | Source/evidence | Requirement / invariant | Owning tasks | Focused tests |
|---|---|---|---|---|---|
| R-EXP-001 | Only current non-deleted Expense rows contribute to current totals | `AGENTS.md`; `financial_engine.py`; C-03/C-05 | FR-003-020; FR-005-012; INV-EXP-001; INV-REV-001 | T003-007, T003-008, T005-003, T005-004 | `CurrentExpenseDatasetTest`, `EconomicDatasetQueryTest` |
| R-EXP-002 | Expense kind is Ordinary or Plafond | `mpit_expense.py::_validate_kind_rules` | FR-003-001; INV-EXP-002 | T003-003, T003-004, T003-010, T003-011 | `ExpenseSchemaTest`, `CreateExpenseTest`, `UpdateExpenseTest` |
| R-EXP-003 | Estimate, Quote and Actual are independent | Q-037 | FR-003-010–012; FR-005-013; INV-EXP-003; INV-ECO-002 | T003-010–T003-012, T005-001, T005-002 | `CreateExpenseTest`, `EconomicEngineTest` |
| R-ACT-001 | Actual is correctable, versionable, restorable and deletable | Q-006; C-05/C-12 | FR-003-030–035; INV-REV-001–003 | T003-013–T003-018 | `ConfirmActualTest`, `ExpenseRevisionHistoryTest`, `RestoreExpenseRevisionTest`, `DeleteExpenseTest` |
| R-ACT-002 | Generated Actual starts ToConfirm and becomes user-authoritative after edit/confirmation | Q-035; C-13 | FR-003-032; FR-004-025–029; INV-ACT-001; INV-CON-007–008 | T003-013, T003-014, T004-010, T004-011 | `GeneratedActualOwnershipTest`, `ContractOccurrenceSynchronizationTest` |
| R-AMT-001 | Unit price derives amount; otherwise amount is manual | `mpit_expense.py::_sync_row_amounts` | FR-003-040; INV-AMT-001 | T003-001, T003-002, T003-010, T003-011 | `MoneyTest`, `ExpenseCalculationTest` |
| R-VAT-001 | Non-zero amount requires VAT/default and Net + VAT = Gross | `tax.py::validate_strict_vat`; C-02 | FR-003-041–042; INV-VAT-001; INV-BAS-001 | T003-001, T003-002, T003-010, T003-011 | `VatCalculatorTest`, `ExpenseCalculationTest` |
| R-DATE-001 | Single spend date xor complete period | `mpit_expense.py::_validate_row` | FR-003-050; INV-DATE-001 | T003-001, T003-002, T003-010, T003-011 | `MonthlyAllocatorTest`, `CreateExpenseTest` |
| R-DIST-001 | All/start/end allocation reconciles exactly | `financial_engine.py::allocate_expense_row_to_months` | FR-003-051; INV-DIST-001 | T003-001, T003-002, T003-010, T003-011 | `MonthlyAllocatorTest`, `ExpenseCalculationTest` |
| R-PLF-001 | Extra and Plafond funding are mutually exclusive | `mpit_expense.py::_validate_kind_rules` | FR-003-013; INV-PLF-001 | T003-010, T003-011 | `CreateExpenseTest`, `UpdateExpenseTest` |
| R-PLF-002 | Plafond is same tenant/year and may cross cost center | `mpit_expense.py::_validate_plafond_reference` | FR-003-014; INV-PLF-002 | T003-010, T003-011 | `ExpenseCalculationTest` |
| R-PLF-003 | Plafond contribution is allocated + overrun without double count | Q-040; verified legacy summary | FR-005-015; INV-PLF-003 | T005-001, T005-002 | `EconomicEngineTest` |
| R-PRJ-001 | Project stage controls Estimate/Quote bucket | `financial_engine.py::_get_project_bucket`; Q-040 | FR-004-010–012; FR-005-014; INV-PRJ-001/003 | T004-004, T004-005, T005-001, T005-002 | `ProjectBucketFixtureTest`, `EconomicEngineTest` |
| R-PRJ-002 | Actual remains primary despite later project stage | Q-040 | FR-004-012; FR-005-014; INV-PRJ-003/004 | T004-004, T005-001, T005-002 | `ProjectBucketFixtureTest`, `EconomicEngineTest` |
| R-PRJ-003 | Deferred project has target year and explicit promotion | `tasks.py::promote_deferred_projects` | FR-004-015; INV-PRJ-002 | T004-004–T004-006 | `ProjectLifecycleTest`, `PromoteDeferredProjectsCommandTest` |
| R-CON-001 | Contract terms do not overlap | `mpit_contract.py::_validate_terms_no_overlap` | FR-004-020–023; INV-CON-001 | T004-007–T004-009 | `ContractTermOverlapTest`, `ContractLifecycleTest` |
| R-CON-002 | Generation is source-key unique, suppression-aware and no-overwrite | `contract_expense_sync.py`; C-13; PD-GEN-001 | FR-004-025–033; INV-CON-002–008 | T004-010–T004-018 | synchronization, deletion and occurrence-control tests |
| R-CON-003 | Contracts/projects are context or generators, never separate total sources | legacy summary; C-03 | FR-004-011/035; FR-005-010; INV-CON-004; INV-REP-001 | T004-001, T004-004, T005-001–T005-004 | `ContractProjectSchemaTest`, `EconomicEngineTest`, `EconomicDatasetQueryTest` |
| R-BUD-001 | Current Budget is one rolling tenant/year calculation | Q-036; PD-BUD-001 | FR-005-001/011; INV-ECO-001; INV-BUD-003 | T005-003–T005-007 | `EconomicDatasetQueryTest`, `CurrentBudgetParityTest` |
| R-BUD-002 | Deliberate states are immutable named BudgetVersion snapshots | Q-036; C-12 | FR-005-040–047; INV-BUD-001–004 | T005-009–T005-018 | BudgetVersion schema, capture, immutability and comparison tests |
| R-BUD-003 | Manual history may be total-only/partial/full without invented dimensions | Q-034 | FR-005-043–044; INV-BUD-004 | T005-011, T005-012 | `ManualBudgetVersionTest` |
| R-BAS-001 | Tenant Budget basis is Net or Gross; all components remain stored | Q-038 | FR-003-022/042; FR-005-016; INV-BAS-001/002 | T003-001–T003-011, T005-001–T005-007 | VAT/Expense calculation and Budget parity tests |
| R-REP-001 | Screen, KPI, chart, print, CSV and XLSX share one dataset/scope | C-08; Q-019 | FR-005-020–025/070; INV-REP-002–006 | T005-003, T005-004, T005-006–T005-008, T005-021–T005-023 | dataset, parity, scope, CSV/XLSX and Dusk tests |
| R-SCN-001 | Scenarios are explicit non-official tenant datasets | Q-027; C-03/C-08 | FR-005-030; INV-SCN-001 | T005-019, T005-020, T005-016–T005-018 | `ScenarioIsolationTest`, `CompareBudgetSourcesTest` |

## Security, identity, branding and operations rules

| Rule ID | Rule | Source/evidence | Requirement / invariant | Owning tasks | Focused tests |
|---|---|---|---|---|---|
| R-TEN-001 | Protected Administrator plus configurable tenant roles | Q-001–Q-009; C-07 | FR-007-002–008; INV-TEN-001–008 | T001-006, T007-003, T007-008–T007-013 | permission catalogue, role management, ability matrix and IDOR tests |
| R-TEN-002 | Every economic dataset/output contains exactly one tenant | Q-010/Q-019; C-11 | FR-005-022–025; FR-007-016; INV-REP-004–006; INV-TEN-006/007 | T005-003–T005-004, T005-021–T005-023, T007-011–T007-015 | dataset authorization, output scope, report isolation and no-cross-tenant-economics tests |
| R-TEN-003 | Context fails closed and never leaks between requests/commands | Q-004/Q-015/Q-016; C-11 | FR-007-004/008/012/014; INV-TEN-001–003 | T007-003, T007-004, T007-012, T001-007 | context/team/middleware/query tests |
| R-USR-001 | User deactivation preserves tenant-owned authorship/audit history and reports open assignments for manual reassignment | Q-017; Feature 007 AC-007-08/TEST-007-009; C-05/C-11 | FR-007-015; INV-TEN-009 | T007-008, T007-009 | `TenantUserMembershipTest` deactivation history, tenant ownership and reassignment-needed ID assertions |
| R-AUD-001 | Audit retention is global configurable default 24; lowering is reinforced; pruning cannot delete current business rows, revision identity or named BudgetVersion rows; permission-controlled view exists; no export | Q-020; Feature 007 AC-007-10/TEST-007-010; C-05 | FR-001-019–021; FR-007-017; FR-007-023; INV-PLT-007; INV-TEN-010 | T001-019–T001-021, T001-025–T001-027 | `AuditRetentionTest` protected-record boundary assertions plus settings, scheduler and audit-view authorization/minimization tests |
| R-PWD-001 | No public password recovery; Administrator reset, self-change and emergency interactive reset invalidate sessions without exposing secrets | Q-026; Feature 001/007 acceptance; C-05/C-07 | FR-001-014–017; FR-007-018; FR-007-019; INV-PLT-006 | T001-008, T001-012–T001-016 | no-recovery-route, password administration, self-change and console reset tests |
| R-BRD-001 | Optional tenant branding is Administrator-managed and appears only in selected-tenant report/output content; shell remains Master Plan IT | FR-007-021; Feature 007 data model/plan; Feature 005 output plan | FR-007-021; INV-TEN-001; INV-TEN-004; INV-TEN-006 | T007-001, T007-002, T007-019, T007-020, T005-021–T005-023 | tenant branding, private logo, branding settings, output branding and print tests |
| R-ATT-001 | Attachments follow current parent permission and private lifecycle | Q-018 | FR-003-061/062; INV-TEN-003 | T003-021–T003-023, T003-017, T003-018 | attachment schema/policy/download/lifecycle/Livewire tests |
| R-NOT-001 | Database-first synchronous notifications, optional email, no worker | Q-023; C-06 | FR-001-006/018; FR-004-038; FR-006-018; INV-PLT-004; INV-OPS-004 | T001-017, T001-018, T004-019, T004-020, T006-014, T001-025 | dedup/mail failure, renewal, operation failure and scheduler tests |
| R-MIG-001 | One legacy site imports into one immutable selected tenant | Q-011/Q-022/Q-029; C-09 | FR-006-001–013/017/019; FR-007-020; INV-MIG-001–005 | T006-001–T006-009, T007-016, T007-017 | package, dry-run, collision, reconciliation, apply and tenant ownership tests |
| R-PORT-001 | Tenant portability excludes audit, secrets and global settings | Q-021; C-05/C-09 | FR-006-015/016/019; INV-OPS-002/003 | T006-010, T006-011 | export package, round-trip and exclusion tests |
| R-BKP-001 | Installation backup is Created then Verified by empty restore rehearsal | Q-021/Q-024 | FR-006-010/011/014/017/019; INV-OPS-001/002 | T006-012, T006-013 | dependency gate, backup/restore contract and BackupRun tests |
| R-DEP-001 | Production consumes one immutable prebuilt artifact | C-06; development/test contract | FR-001-005; FR-006-012; INV-PLT-003 | T001-022, T001-023, T006-015, T006-016 | workflow, release artifact, preflight and immutable deployment tests |

## Requirement coverage ledger

| Feature | Requirement ranges | Owning task groups | Verification task |
|---|---|---|---|
| 001 | FR-001-001–004 | T001-007–T001-016 | T001-024 |
| 001 | FR-001-005–018 | T001-001–T001-018, T001-022–T001-027 | T001-024 |
| 001 | FR-001-019–021 | T001-019–T001-021, T001-025–T001-027 | T001-024 |
| 002 | FR-002-001–002 | T002-007–T002-009 | T002-016 |
| 002 | FR-002-003–005/008–009 | T002-010–T002-012 | T002-016 |
| 002 | FR-002-006–007 | T002-013–T002-015 | T002-016 |
| 002 | FR-002-010–013 | T002-001–T002-016 | T002-016 |
| 003 | FR-003-001–002; FR-003-010–014; FR-003-040–042; FR-003-050–052; FR-003-063 | T003-001–T003-012 | T003-020 |
| 003 | FR-003-020–022/060 | T003-007–T003-009 | T003-020 |
| 003 | FR-003-030–035 | T003-013–T003-018 | T003-020 |
| 003 | FR-003-061–062 | T003-021–T003-023, T003-017–T003-018 | T003-020 |
| 004 | FR-004-001/010–015/037 | T004-001–T004-006 | T004-021 |
| 004 | FR-004-020–023/038 | T004-007–T004-009, T004-019–T004-020 | T004-021 |
| 004 | FR-004-025–039 except FR-004-038 | T004-010–T004-018 | T004-021 |
| 005 | FR-005-001–003; FR-005-010–016; FR-005-050; FR-005-070 | T005-001–T005-008 | T005-025 |
| 005 | FR-005-020–025/060 and cross-feature FR-007-021 | T005-003–T005-004, T005-021–T005-023, T007-019–T007-020 | T005-025 |
| 005 | FR-005-030 | T005-019–T005-020 | T005-025 |
| 005 | FR-005-040–047 | T005-009–T005-018 | T005-025 |
| 006 | FR-006-001–009/013/017/019 | T006-001–T006-009, T006-017 | T006-018 |
| 006 | FR-006-010–012/014 | T006-012–T006-017 | T006-018 |
| 006 | FR-006-015–016 | T006-010–T006-011 | T006-018 |
| 006 | FR-006-018 | T006-014 | T006-018 |
| 007 | FR-007-001–015 | T007-001–T007-013, T007-019–T007-020 | T007-018 |
| 007 | FR-007-016/022 | T007-014–T007-015 | T007-018 |
| 007 | FR-007-017/023 | T001-019–T001-021, T001-025–T001-027 | T007-018 |
| 007 | FR-007-018/019 | T001-008, T001-012–T001-016 | T007-018 |
| 007 | FR-007-020 | T007-016–T007-017 | T007-018 |
| 007 | FR-007-021 | T007-001, T007-002, T007-019, T007-020, T005-021–T005-023 | T007-018 |

## Coverage gate

No row permits implementation of unstated behavior. A task is executable only when its owning task entry, readiness row, exact command and any required path expansion agree. `/speckit.analyze` must be repeated after this remediation and must report CRITICAL `0` and HIGH `0` before `/speckit.implement`.
