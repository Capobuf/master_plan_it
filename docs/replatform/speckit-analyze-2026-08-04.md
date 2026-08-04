# `/speckit.analyze` — integrated Laravel replatform

Status: `FAILED — IMPLEMENTATION REMAINS BLOCKED`  
Mode: read-only cross-artifact analysis  
Analysis date: 2026-08-04  
Analyzed branch: `laravel-replatform`  
Analyzed commit: `8911832f0cb31d30286dc7bfe9a67c4dfadf0a69`  
Constitution: 3.0.1

## Result

| Severity | Count | Gate |
|---|---:|---|
| CRITICAL | 1 | must be 0 |
| HIGH | 5 | must be 0 |
| MEDIUM | 4 | resolve or disposition |
| LOW | 0 | — |

`/speckit.implement` remains blocked.

## Method and command compatibility

The upstream Codex `$speckit-analyze` skill was loaded in full. Its required command was executed once from the repository root:

```bash
.specify/scripts/bash/check-prerequisites.sh --json --require-tasks --include-tasks
```

It exited `1` with `Feature directory not found` because upstream Spec Kit v0.15.2 resolves one active feature through `SPECIFY_FEATURE_DIRECTORY` or `.specify/feature.json`, while this branch intentionally integrates seven feature directories. No feature was selected implicitly and no `.specify/feature.json` was written. The equivalent prerequisites were then verified for all seven directories: every feature has `spec.md`, `plan.md`, `research.md`, `data-model.md`, contracts, `tasks.md`, `quickstart.md` and checklists.

The analysis compared the Constitution, Q-001–Q-041, cross-cutting contracts, plans, physical models, all Feature 001–007 artifacts, task/readiness/command/path registries, previous analyze reports, current GitHub workflow disposition, installed Spec Kit integration and the authorized legacy evidence at `e1f6dd2f770dbbdd0b5739ac7da4a575ec142bb3`.

## Findings

| ID | Category | Severity | Location(s) | Summary | Required remediation |
|---|---|---|---|---|---|
| ANALYZE3-C-001 | Constitution conflict | CRITICAL | `specs/003-expense-domain/contracts/authorization.md:53`; C-05; Q-006; FR-003-030/031 | The authorization contract says recorded Actual and economic history cannot be modified or deleted. The approved target explicitly makes Actual correctable, restorable and deletable with permission while history remains immutable. | Replace the stale fixed-role clause with permission-based current-record rules. Preserve immutable prior revisions and all tenant/economic invariants. |
| ANALYZE3-H-001 | Task underspecification | HIGH | T003-005, T005-006, T006-014, T007-010 | These tasks still require the implementation agent to infer paths or symbols: Expense model allowlists; a bare `TenantDashboardTest.php`; unspecified owning command failure branches; and unspecified versioned Policies. | Add every exact repository-relative target and named symbol once in the path manifest or owning task, including all affected command and Policy classes. |
| ANALYZE3-H-002 | Verification-task readiness | HIGH | T001-024, T002-016, T003-020, T004-021, T005-025, T006-018, T007-018 | Final `[VER]` tasks say to update quickstarts, contracts, catalogues or traceability but do not name the exact document paths or sections/no-runtime symbols. | Enumerate exact documentation targets and state-update symbols. Keep `[VER]` free of runtime behavior. |
| ANALYZE3-H-003 | Invalid requirement references | HIGH | Feature 003/004/005 task ranges; `source-traceability.md:65,72` | Compact ranges cross undefined stable IDs, for example FR-003-040–052, FR-004-010–015, FR-005-001–016 and feature-wide final ranges. This falsely references requirements that do not exist. | Replace every sparse range with the exact defined IDs. Do the same in the coverage ledger and final verification tasks. |
| ANALYZE3-H-004 | Non-stable task requirement | HIGH | `specs/003-expense-domain/tasks.md:57` | T003-019 lists narrative `migration notes` as a requirement alongside stable IDs. | Map the legacy lifecycle fixture to exact FR/INV/source rule IDs; keep explanatory migration notes only as a source link. |
| ANALYZE3-H-005 | Normative state conflict | HIGH | `deepening-audit.md`, `roadmap.md`, `screen-and-report-inventory.md`, `spec-kit-methodology.md`, `target-architecture.md`, feature clarification-result paragraphs | Current reading-order artifacts still assert open Q-016–Q-024, a three-role foundation, target replacement UI, non-persistent scenarios, a possible `ReportPdfRenderer`, no Spec Kit CLI use, or that plans/tasks still require regeneration. These contradict approved/current artifacts and can select the wrong command or product behavior. | Mark historical audits explicitly superseded or update current normative statements without changing Q-001–Q-041. |
| ANALYZE3-M-001 | Phase metadata | MEDIUM | root/replatform README; artifact/package/readiness summaries | Several documents still point at `eb75be0…`, say integration is only PR metadata, or identify the next analyze as not yet run. | Update only after this report and its remediation are integrated; keep live GitHub state out of immutable historical reports. |
| ANALYZE3-M-002 | Checklist quality | MEDIUM | 13 files under `specs/*/checklists/` | All 192 checklist items are unchecked and most checklists duplicate a generic cross-feature template rather than posing scope-specific verifiable questions. | Regenerate/evaluate checklists by feature after task remediation; do not check the zero-HIGH item until the final analysis passes. |
| ANALYZE3-M-003 | Output-language ambiguity | MEDIUM | `implementation-readiness.md:60`; Q-013/Q-039 | “Application UI remains initially Italian” can be read as a current language constraint even though Q-039 is superseded and tenant-facing output follows configured tenant language. | State the approved shell/output boundary exactly and remove the superseded Italian-only implication. |
| ANALYZE3-M-004 | Historical/current labelling | MEDIUM | `deepening-audit.md`; `spec-kit-methodology.md`; prior self-checks | Historical evidence is useful but lacks a prominent `DEPRECATED`/superseded boundary and appears in the current reading set. | Preserve it as evidence, add explicit supersession headers and link to the current analysis/state documents. |

## Coverage summary

| Feature | FR | NFR | INV | Task IDs | Assessment |
|---|---:|---:|---:|---:|---|
| 001 Platform foundation | 21 | 5 | 8 | 27 | nominal coverage; final verification target paths incomplete |
| 002 Master data | 13 | 0 | 7 | 16 | nominal coverage; final verification target paths incomplete |
| 003 Expense domain | 26 | 0 | 16 | 23 | blocked by constitutional conflict, sparse ranges and one narrative requirement |
| 004 Contracts/projects | 24 | 0 | 13 | 21 | nominal coverage; sparse ranges and final verification target paths incomplete |
| 005 Reporting/analytics | 28 | 0 | 17 | 25 | nominal coverage; sparse ranges and exact-path gaps |
| 006 Migration/operations | 19 | 0 | 9 | 18 | nominal coverage; exact failure-branch and verification-document gaps |
| 007 Tenancy/access | 23 | 6 | 10 | 20 | nominal coverage; Policy and final verification targets incomplete |
| **Total** | **154** | **11** | **80 feature declarations / 76 unique IDs** | **150** | exact bidirectional coverage not yet proven |

All 150 task IDs are unique. All 150 resolve to one exact validation-command row after expanding the command-register ranges. The dependency graph has no missing task ID and no cycle. There are 42 `[P]` markers. These structural successes do not override the findings above.

## Constitution alignment

ANALYZE3-C-001 violates C-05 and the amendment procedure because a lower-authority contract silently restores superseded immutable-Actual behavior. ANALYZE3-H-001 through H-004 violate C-01 and the task Definition of Ready by requiring path, symbol or stable-ID inference.

No Constitution amendment or Product Owner decision is required. The approved decisions already determine every correction.

## Unmapped or invalid task references

- T003-019: narrative `migration notes` is not a requirement ID.
- Sparse FR ranges in Features 003–005 include undefined IDs and therefore cannot be treated as mapped requirements.
- The seven terminal verification tasks have commands but lack exact documentation mutation targets.

## Metrics

- Functional requirements: 154
- Non-functional requirements: 11
- Unique invariant IDs: 76
- Tasks: 150
- Tasks with command coverage: 150 (100%)
- Missing dependencies: 0
- Dependency cycles: 0
- Parallel markers: 42
- Checklist files/items completed: 13 / 0 of 192
- Critical issues: 1
- High issues: 5
- Medium issues: 4

## Next actions

1. Run `$speckit-tasks` to correct task paths, exact requirements and terminal verification targets.
2. In the same bounded documentation remediation, reconcile the stale Feature 003 authorization contract and explicitly supersede/update misleading current-state documents; no product decision changes.
3. Run `$speckit-checklist` to make the checklists feature-specific and verifiable.
4. Integrate the remediation into `laravel-replatform` and run `$speckit-analyze` again.

## Not performed

No application code, dependency resolution, Laravel scaffold, migration, application test, workflow run, build, backup, restore, import, deployment, cutover or `$speckit-implement` command was executed.
