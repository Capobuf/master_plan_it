# Bidirectional source traceability

| Rule ID | Rule | Frappe source | Target requirement | Invariant | Component | Test | Task |
|---|---|---|---|---|---|---|---|
| R-EXP-001 | Only active expense rows contribute | AGENTS.md; financial_engine.py | FR-003-020 | INV-EXP-001 | EconomicPositionQuery | TEST-003-020 | T003-08 |
| R-EXP-002 | Ordinary/Plafond only | mpit_expense.py::_validate_kind_rules | FR-003-001 | INV-EXP-002 | CreateExpense | TEST-003-001 | T003-05 |
| R-EXP-003 | Project and contract mutually exclusive | mpit_expense.py::_validate_kind_rules | FR-004-031 | INV-EXP-003 | LinkExpenseContext | TEST-004-031 | T004-08 |
| R-PLF-001 | Plafond and Extra mutually exclusive | mpit_expense.py::_validate_kind_rules | FR-003-011 | INV-PLF-001 | SaveExpenseFunding | TEST-003-011 | T003-06 |
| R-PLF-002 | Cross-cost-center allowed, same year required | mpit_expense.py::_validate_plafond_reference | FR-003-012 | INV-PLF-002 | ValidatePlafondReference | TEST-003-012 | T003-06 |
| R-ROW-001 | Actual cannot be replaced | mpit_expense.py::_validate_row_replacements | FR-003-031 | INV-ROW-001 | ReplaceExpenseRow | TEST-003-031 | T003-09 |
| R-ROW-002 | Replacement cycle forbidden | mpit_expense.py::_validate_replacement_cycles | FR-003-032 | INV-ROW-002 | ReplaceExpenseRow | TEST-003-032 | T003-09 |
| R-AMT-001 | Unit price derives amount; otherwise manual | mpit_expense.py::_sync_row_amounts | FR-003-040 | INV-AMT-001 | CalculateExpenseRowAmounts | TEST-003-040 | T003-04 |
| R-VAT-001 | Non-zero amount requires VAT/default | tax.py::validate_strict_vat | FR-003-041 | INV-VAT-001 | VatCalculator | TEST-003-041 | T003-04 |
| R-DATE-001 | Single spend date xor complete period | mpit_expense.py::_validate_row | FR-003-050 | INV-DATE-001 | AllocateExpenseRow | TEST-003-050 | T003-07 |
| R-DIST-001 | all/start/end monthly allocation | financial_engine.py::allocate_expense_row_to_months | FR-003-051 | INV-DIST-001 | MonthlyAllocator | TEST-003-051 | T003-07 |
| R-PRJ-001 | Project stage controls reporting bucket | financial_engine.py::_get_project_bucket | FR-004-010 | INV-PRJ-001 | ProjectStage | TEST-004-010 | T004-03 |
| R-PRJ-002 | Deferred promoted to Proposed | tasks.py::promote_deferred_projects | FR-004-015 | INV-PRJ-002 | PromoteDeferredProjects | TEST-004-015 | T004-06 |
| R-CON-001 | Terms cannot overlap | mpit_contract.py::_validate_terms_no_overlap | FR-004-020 | INV-CON-001 | SaveContractTerms | TEST-004-020 | T004-04 |
| R-CON-002 | Contract sync adds missing, never overwrites | contract_expense_sync.py::_sync_contract_year_expense | FR-004-025 | INV-CON-002 | SynchronizeContractExpenses | TEST-004-025 | T004-07 |
| R-CON-003 | Contract totals not independently added | financial_engine.py::get_cost_center_financial_summary | FR-005-010 | INV-REP-001 | EconomicPositionQuery | TEST-005-010 | T005-04 |
| R-TEN-001 | Product roles converge to Administrator, Editor, Viewer | Q-001 | FR-007-002; FR-001-003 | INV-TEN-003 | Policies and tenant context | TEST-007-003 | T007 planning |
| R-TEN-002 | Tenant business data is isolated and other-tenant access denied | Q-002, Q-010 | FR-007-010; FR-007-016; cross-feature tenant FRs | INV-TEN-001; INV-TEN-004 | Tenant ownership and scoped queries | TEST-007-001; TEST-007-004 | T007 planning |
| R-TEN-003 | Administrator retains identity and explicitly selects tenant | Q-004, Q-015 | FR-007-004; FR-007-015 | INV-TEN-002 | Tenant context/audit | TEST-007-002 | T007 planning |
| R-TEN-004 | Editor and Viewer permissions are feature-specific and tenant-bound | Q-005–Q-009 | FR-007-005–FR-007-009; feature authorization contracts | INV-TEN-003 | Policies | TEST-007 role matrix | Cross-feature tenant tasks |
| R-TEN-005 | Global overview is operational and has no economic aggregation | Q-014 | FR-007-014; FR-005-032 | INV-TEN-007; INV-REP-005 | GlobalTenantOverviewQuery | TEST-007-007; TEST-005-032 | T005-14 |
| R-MIG-002 | One Frappe site imports into one selected tenant manually or by CSV | Q-011 | FR-007-011; FR-006-013 | INV-MIG-004 | TenantImportAction | TEST-006-013 | T006-14 |
