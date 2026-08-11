# Requirements quality checklist — Feature 010 Projects

**Purpose**: reviewer gate for completeness, clarity, consistency and traceability before implementation.
**Created**: 2026-08-09
**Feature**: [spec.md](../spec.md)

## Requirement completeness

- [x] CHK001 Are all five user stories defined as vertical user outcomes with independent test criteria? [Completeness, Spec §User stories]
- [x] CHK002 Are all FR-010-001..016 retained and connected to acceptance scenarios? [Traceability, Spec §Requisiti funzionali]
- [x] CHK003 Are Project fields and explicit non-monetary boundaries documented? [Completeness, Spec §FR-010-001, §FR-010-005]
- [x] CHK004 Are Project/Contract exclusivity and Contract-generated Expense protection specified? [Coverage, Spec §FR-010-010, §Edge cases]
- [x] CHK005 Are terminal delete, linked Expense guard and deletion-reason semantics complete? [Completeness, Spec §US-010-05]

## Requirement clarity and consistency

- [x] CHK006 Are technical stage values distinguished from Italian presentation labels without changing product semantics? [Clarity, Spec §FR-010-002]
- [x] CHK007 Is Deferred promotion timing defined using target year and Tenant-local year, including idempotency? [Clarity, Spec §US-010-02]
- [x] CHK008 Are Actual-always-Primary and Estimate/Quote mappings unambiguous for every stage? [Clarity, Spec §US-010-03]
- [x] CHK009 Is Potential consistently defined as non-official and exclusive of Excluded? [Consistency, Spec §FR-010-009]
- [x] CHK010 Are Project-as-context and Expense-as-source consistent across scope, acceptance and success criteria? [Consistency, Spec §Obiettivo, §SC-010-003]

## Scenario and failure coverage

- [x] CHK011 Are permission, tenant isolation, inactive context, stale version and atomic failure requirements covered? [Coverage, Spec §SC-010-004]
- [x] CHK012 Are revision history, compare, restore, source immutability and terminal deletion boundaries specified? [Coverage, Spec §US-010-04]
- [x] CHK013 Are Plafond non-regression and no-double-counting requirements explicit? [Coverage, Spec §US-010-03/AC6]
- [x] CHK014 Are UI loading, empty, error, long-title and responsive states included without moving authority to React? [Coverage, Spec §Edge cases]
- [x] CHK015 Are rollback/no-side-effect expectations defined for conflicting mutations? [Recovery, Spec §US-010-05/AC1]

## Dependencies and boundaries

- [x] CHK016 Are Feature 008/009 dependencies bounded to reusable current primitives? [Dependency, Spec §Dipendenze e assunzioni]
- [x] CHK017 Are forbidden scope expansions (totals, workflow, new engines/frameworks) explicit? [Boundary, Spec §Fuori scope]
- [x] CHK018 Are acceptance outcomes measurable without requiring undocumented product choices? [Measurability, Spec §Success criteria]

## Notes

All items pass after repository review. No `[NEEDS CLARIFICATION]` marker remains.
