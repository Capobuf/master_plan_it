# Bidirectional source traceability

Status: `PRODUCT TRACEABILITY CURRENT — IMPLEMENTATION MAPPING REQUIRES /speckit.plan AND /speckit.tasks`

Legacy sources establish verified behavior or migration evidence. Product-owner decisions and Constitution 3.0.1 establish target changes. Component names below are bounded planning directions, not implemented symbols. Every task remains pending until regenerated.

| Rule ID | Rule | Source/evidence | Target requirement | Invariant | Planned owner | Test | Task |
|---|---|---|---|---|---|---|---|
| R-EXP-001 | Only current non-deleted Expense rows contribute to current totals | AGENTS.md; `financial_engine.py`; Constitution C-03/C-05 | FR-003-020; FR-005-012 | INV-EXP-001; INV-REV-001 | `EconomicDatasetQuery` | TEST-003-001; TEST-003-007 | PENDING |
| R-EXP-002 | Expense kind is Ordinary or Plafond | `mpit_expense.py::_validate_kind_rules` | FR-003-001 | INV-EXP-002 | Expense Actions | TEST-003-002 | PENDING |
| R-EXP-003 | Estimate, Quote and Actual are independent; no mandatory progression | Q-037 | FR-003-010–012; FR-005-013 | INV-EXP-003; INV-ECO-002 | Expense editor + economic kernel | TEST-003-003; TEST-005-008 | PENDING |
| R-ACT-001 | Actual is correctable, versionable, restorable and deletable by permission | Q-006; Constitution C-05/C-12 | FR-003-030–035 | INV-REV-001–003 | Expense revision Actions | TEST-003-007–010 | PENDING |
| R-ACT-002 | Generated Actual begins Da confermare; manual edit/confirmation stops automatic overwrite without permanent immutability | Q-035; PD-GEN-001 | FR-003-032; FR-004-025–029 | INV-ACT-001; INV-CON-007–008 | Expense confirmation + contract sync Actions | TEST-003-010; TEST-004-010–011 | PENDING |
| R-AMT-001 | Unit price derives amount; otherwise amount is manual | `mpit_expense.py::_sync_row_amounts` | FR-003-040 | INV-AMT-001 | Money calculator | TEST-003-011 | PENDING |
| R-VAT-001 | Non-zero amount requires VAT/default and Net + VAT = Gross | `tax.py::validate_strict_vat`; Constitution C-02 | FR-003-041–042 | INV-VAT-001; INV-BAS-001 | VAT calculator | TEST-003-012–013 | PENDING |
| R-DATE-001 | Single spend date xor complete period | `mpit_expense.py::_validate_row` | FR-003-050 | INV-DATE-001 | Monthly allocator | TEST-003-014 | PENDING |
| R-DIST-001 | all/start/end allocation reconciles exactly | `financial_engine.py::allocate_expense_row_to_months` | FR-003-051 | INV-DIST-001 | Monthly allocator | TEST-003-015 | PENDING |
| R-PLF-001 | Extra and Plafond funding are mutually exclusive | `mpit_expense.py::_validate_kind_rules` | FR-003-013 | INV-PLF-001 | Expense funding Action | TEST-003-005 | PENDING |
| R-PLF-002 | Plafond is same tenant/year and may cross cost center | `mpit_expense.py::_validate_plafond_reference` | FR-003-014 | INV-PLF-002 | Plafond validation | TEST-003-006 | PENDING |
| R-PLF-003 | Plafond primary contribution is allocated + overrun; covered consumption is not double counted | Q-040; verified legacy summary behavior | FR-005-015 | INV-PLF-003 | `EconomicEngine` | TEST-005-010 | PENDING |
| R-PRJ-001 | Project stage controls Estimate/Quote bucket | `financial_engine.py::_get_project_bucket`; Q-040 | FR-004-010–012; FR-005-014 | INV-PRJ-001; INV-PRJ-003 | `EconomicEngine` | TEST-004-001; TEST-004-003; TEST-005-009 | PENDING |
| R-PRJ-002 | Actual attributed to the year remains primary regardless of later project stage | Q-040 | FR-004-012; FR-005-014 | INV-PRJ-003; INV-PRJ-004 | `EconomicEngine` | TEST-004-003; TEST-005-009 | PENDING |
| R-PRJ-003 | Deferred project has target year and promotion rule | legacy `tasks.py::promote_deferred_projects` | FR-004-015 | INV-PRJ-002 | Project Action/command | TEST-004-002 | PENDING |
| R-CON-001 | Contract terms do not overlap | `mpit_contract.py::_validate_terms_no_overlap` | FR-004-020–023 | INV-CON-001 | Contract term Action | TEST-004-004 | PENDING |
| R-CON-002 | Contract sync is idempotent, source-key unique, suppression-aware and no-overwrite | `contract_expense_sync.py`; PD-GEN-001; C-13 | FR-004-025–033 | INV-CON-002–008 | Contract generation Actions | TEST-004-005–011 | PENDING |
| R-CON-003 | Contracts and projects are context/generators, never independent total sources | `financial_engine.py::get_cost_center_financial_summary`; C-03 | FR-004-011; FR-004-035; FR-005-010 | INV-CON-004; INV-REP-001 | Economic query/kernel | TEST-004-007; TEST-005-001 | PENDING |
| R-BUD-001 | Current Budget is one rolling tenant/year calculation without synchronized current total | Q-036; PD-BUD-001 | FR-005-001; FR-005-011 | INV-ECO-001; INV-BUD-003 | Economic kernel + Budget page query | TEST-005-007; TEST-005-014 | PENDING |
| R-BUD-002 | Deliberate states are immutable named BudgetVersion snapshots | PD-BUD-001; C-12; Q-036 | FR-005-040–047 | INV-BUD-001–004 | BudgetVersion Actions/queries | TEST-005-012–015 | PENDING |
| R-BUD-003 | Historical manual versions may be total-only/partial/full without invented dimensions or approval | Q-034 | FR-005-043–044 | INV-BUD-004 | BudgetVersion manual draft | TEST-005-015 | PENDING |
| R-BAS-001 | Tenant official Budget basis is Net or Gross, default Net; all components remain stored | Q-038 | FR-003-022/042; FR-005-016 | INV-BAS-001–002 | Tenant setting + economic dataset | TEST-003-013; TEST-005-011 | PENDING |
| R-REP-001 | Screen, KPI, chart, print, CSV and XLSX share one semantic dataset for selected scope | Constitution C-08; Q-019 | FR-005-020–025; FR-005-070 | INV-REP-002–006; INV-ECO-001 | Economic dataset + output adapters | TEST-005-002–007 | PENDING |
| R-SCN-001 | Scenarios are explicit non-official tenant datasets and never enter current totals | Q-027; C-03/C-08 | FR-005-030 | INV-SCN-001 | Scenario query/adapter | TEST-005-016 | PENDING |
| R-TEN-001 | Protected Administrator plus configurable tenant roles; role names do not define business rules | Q-001–Q-009; C-07 | FR-007-002–008 | INV-TEN-001–008 | Policies/Gates + tenant context | TEST-007-001–008 | PENDING |
| R-TEN-002 | Every economic dataset/output contains exactly one tenant | Q-010; Q-014; Q-019; C-11 | FR-005-022–025; FR-007-016 | INV-REP-004–006; INV-TEN-006–007 | Scoped queries and output policies | TEST-005-004–006; TEST-007-006–007 | PENDING |
| R-MIG-001 | One Frappe site imports into one selected tenant with quarantine/reconciliation | Q-011; Q-022; Q-029; C-09 | FR-007-020; Feature 006 contracts | Migration invariants | Migration staging/Actions | PENDING PLAN TEST IDS | PENDING |

## Regeneration gate

`/speckit.plan` must replace planned-owner labels with final files/symbols and complete the physical migration/test mapping. `/speckit.tasks` must replace every `PENDING` task with stable dependency-ordered IDs. No row in this table is permission to implement a guessed symbol.
