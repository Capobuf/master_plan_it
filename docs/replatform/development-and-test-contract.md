# Development and test contract

Status: `APPROVED TECHNICAL CONTRACT — PROPAGATED TO CURRENT PLANS AND TASKS`
Scope: development environment, test architecture, CI quality gates, release artifact handoff  
Decision date: 2026-08-03  
Authority: Constitution 5.0.0 and clarified Features 001–007

## 1. Purpose and boundary

This contract defines how implementation work is developed and verified. Current dependency targets and validation gates are propagated through the integrated research, plans, and tasks; this contract does not implement Laravel code or override those feature-owned artifacts.

The environment and test system must expose failures rather than hide them. No command may silently reset data, select a fallback runtime, skip a required suite, or publish an artifact from an unverified commit.

## 2. Canonical development environment

Laravel Sail is the authoritative local and agent-verification environment.

Minimum Compose topology:

- `laravel.test`: Laravel runtime, Composer, Vite build tools and quality tools;
- `mysql`: one persistent MySQL service;
- `selenium`: optional profile used only by the bounded Dusk suite.

Redis, Mailpit, Meilisearch, MinIO, WebSockets and permanent queue workers are excluded unless a later approved requirement proves a concrete need.

Native PHP or another local convenience environment may be used during editing, but a change is not verified until the relevant Sail commands pass.

The exact PHP branch, image tag/digest, Sail release, Node build version and MySQL image tags are selected by the integrated plans and must be locked by their owning setup tasks after checking current Laravel 13 compatibility and the production-hosting floor. Floating `latest` tags are prohibited.

## 3. Database compatibility contract

The target database baseline must include MySQL 8.4 LTS. Support for an additional current MySQL family is accepted only when `/speckit.plan` verifies current support, hosting relevance and CI cost.

All supported profiles use:

- InnoDB;
- `utf8mb4`;
- strict SQL mode;
- the common supported SQL subset;
- identical application migrations.

A database version update requires migration, accounting and representative application compatibility checks. No MySQL-family fallback is silent.

## 4. Separate non-destructive test database

Development and tests use the same MySQL service and configuration but separate logical databases:

- `master_plan_it`: local development;
- `master_plan_it_test`: automated tests, including Dusk.

The test database persists between runs. This is a project-specific constraint, not Laravel's default database-testing model.

Forbidden in test code, Composer scripts and workflows:

- `migrate:fresh`;
- `db:wipe`;
- `RefreshDatabase`;
- `LazilyRefreshDatabase`;
- `DatabaseMigrations`;
- `DatabaseTruncation`;
- unscoped `TRUNCATE`, `DROP TABLE` or `DROP DATABASE`;
- any implicit database recreation from an individual test.

`composer test:prepare` may:

1. require `APP_ENV=testing`;
2. require the exact configured test database name;
3. verify the expected MySQL connection and SQL mode;
4. run forward-only `artisan migrate --env=testing`.

It must fail before writing when the environment or database is wrong.

Database tests use transaction rollback when application and test share one process. Cross-process/browser tests create records with a unique run identifier and delete only those records they created where the current domain contract permits deletion. Browser tests run sequentially unless the later plan proves isolated process databases without weakening this contract.

A focused architecture test rejects destructive reset helpers and commands. This is a bounded safety guard, not a generic database-security subsystem.

## 5. Test layers

### Layer 1 — Static and structural

Fast checks that do not require MySQL:

- `composer validate --strict`;
- PHP syntax validation;
- Pint check mode;
- PHPStan/Larastan at the configured blocking level;
- Pest architecture tests;
- frontend type/lint checks;
- Vite production build and manifest verification;
- deterministic dependency audit where supported;
- static guards against destructive database resets, skipped/focused mandatory tests, float-based authoritative money, presentation-layer economic formulas and forbidden dependency directions.

### Layer 2 — Accounting

Highest-assurance suite. Pure arithmetic runs without Laravel where possible; persistence, query, concurrency and transaction semantics run on MySQL.

Minimum coverage:

- decimal Money, VAT and rounding boundaries;
- quantity/unit-price multiplication;
- six-decimal intermediate allocations and exact two-decimal results;
- exact monthly residual allocation;
- current non-deleted Estimate, Quote and Actual inclusion rules;
- editable/deletable Actual revision and restore behavior;
- exclusion of operational revisions, audit, deleted tombstones, scenarios, generation exceptions and `BudgetVersion` rows from current totals;
- Plafond capacity, consumption, residual and overrun without double counting;
- project-stage bucket rules;
- contract source-key idempotency, deletion/regeneration choice, suppression, resume, manual missing-year generation and no silent overwrite;
- current, scenario and `BudgetVersion` dataset isolation;
- filtered versus explicit complete report/year scope;
- screen, KPI, chart, print, CSV and XLSX parity for the same selected dataset and scope;
- tenant isolation of every economic dataset.

Expected values come from an approved formula, invariant, `EQ-*` fixture or verified legacy result. The implementation under test never calculates its own expected value. Monetary assertions compare normalized decimal strings exactly.

### Layer 3 — Application

Pest Feature and Inertia/React tests cover routes, middleware, policies, tenant context, configurable permissions, validation, Actions, reports, output scopes, files, operational revisions, audit settings/retention, commands, notifications and scheduler behavior.

Dusk is limited to behavior that cannot be proven reliably below the browser layer:

- JavaScript lifecycle;
- focus and keyboard interaction;
- responsive action menus;
- essential React/Inertia interaction;
- reinforced-confirmation UI;
- print/download smoke.

Dusk does not use screenshot comparison, generated images or video as assertion mechanisms.

## 6. Test ownership and controlled change

Test names include the mapped requirement or invariant ID when available and describe observable behavior.

Comments are in English and explain only invariants, non-obvious fixtures, why a setup is required, or a real trade-off. Obvious arrange/act/assert narration is prohibited.

A production change does not automatically authorize changing expected accounting values. Changes to `tests/Accounting/**`, shared accounting fixtures or required workflow gates must reference the amended requirement, invariant or ADR.

`CODEOWNERS` protects accounting tests and workflow files. Static checks reject accidental `skip`, `todo`, focused-only execution and empty assertions in mandatory suites.

## 7. Execution cadence

During implementation:

1. run the focused test for the changed behavior;
2. run the affected layer before marking the task complete;
3. run `composer verify` before the final commit or PR update.

Required Composer scripts are finalized by `/speckit.plan`, using these stable responsibilities:

- `test:prepare`;
- `test:static`;
- `test:accounting`;
- `test:application`;
- `test:browser`;
- `verify`.

`verify` runs all mandatory non-browser suites and includes browser tests when the task changes React, JavaScript or browser-owned behavior. CI keeps the relevant browser job as an independent required gate.

Initial wall-clock targets are measured after the first vertical slice and may be adjusted only with recorded evidence; they never justify removing required coverage.

## 8. GitHub Actions and artifact handoff

The quality workflow runs required static, accounting, application and browser jobs on pull requests and protected-branch pushes. A database compatibility job verifies every supported MySQL profile without duplicating the full browser suite.

The release workflow:

1. consumes the exact commit that passed all required quality gates;
2. installs production dependencies from the lock file;
3. builds Vite assets;
4. verifies the manifest and package contents;
5. creates one immutable release ZIP;
6. records the source commit and checksums;
7. may create build provenance/attestation when supported by the repository plan.

The production host receives that artifact unchanged and does not run `npm install`, rebuild assets, or select different dependency versions. Feature 006 owns deployment transport, backup, activation, health checks and rollback.

## 9. Required implementation surfaces

The integrated plan and task package map at least:

- `compose.yaml` and required Sail runtime customization;
- `.env.example`, `.env.testing.example`, `phpunit.xml`;
- test environment/database guard;
- `tests/Architecture/DevelopmentContractTest.php`;
- `tests/Accounting/Unit/**`, `tests/Accounting/Integration/**`, fixtures;
- focused `tests/Feature/**`, `tests/Feature/Inertia/**`, `tests/Browser/**`;
- Composer quality scripts;
- `.github/workflows/quality.yml` and release workflow;
- `.github/CODEOWNERS`.

Exact files and symbols are executable only through the current feature-owned `/speckit.plan` and `/speckit.tasks` artifacts plus their task-readiness, execution-command and path-override registries.

## 10. Official references verified 2026-08-03

- Laravel 13 Sail: `https://laravel.com/docs/13.x/sail`;
- Laravel 13 testing: `https://laravel.com/docs/13.x/testing`;
- Laravel 13 database testing: `https://laravel.com/docs/13.x/database-testing`;
- GitHub Actions workflow artifacts: `https://docs.github.com/en/actions/concepts/workflows-and-actions/workflow-artifacts`;
- GitHub build artifact attestations: `https://docs.github.com/en/actions/how-tos/secure-your-work/use-artifact-attestations/use-artifact-attestations`.

These references support the framework/tool capabilities. The non-destructive persistent test-database rule and exact quality layering are project decisions.

## Agent orchestration and model-selection contract

This is the single authoritative matrix for delegated work. `.codex/orchestration-plan.md` records concrete spawns and work packages but references, rather than duplicates, these rules.

| Model / reasoning | Required use | Not the default for |
|---|---|---|
| GPT-5.6 Sol / high | coordination; Spec Kit plan/tasks/analyze; cross-artifact and architecture conflicts; tenancy, authorization, security, Money, economic invariants, revisioning, destructive operations, critical migration; final vertical-slice and economic-dataset-parity review | mechanical edits or repetitive markup |
| GPT-5.6 Sol / medium | first visual reference screen, UX architecture, Budget visual review, complex frontend design judgment without critical invariants | ordinary implementation |
| GPT-5.6 Terra / high | bounded domain Actions, persistence, migrations, economic queries, complex authorized React state, Feature/integration tests and non-mechanical local refactors | mechanical work |
| GPT-5.6 Terra / medium | default implementation for React, Tailwind, Chart.js wiring, simple CRUD, responsive frontend and bounded bugs | critical Money/tenancy/security decisions |
| GPT-5.6 Luna / medium or low | mechanical formatting, repetitive fixtures, UI copy, translation, file lists, simple scans and non-normative repetitive documentation | production domain PHP, authorization, tenancy, Money, migrations, complex React state, non-trivial application JavaScript or technical decisions |

`xhigh` and `max` are forbidden by default. They require a documented failed `high` attempt, evidence that reasoning rather than an incomplete contract caused the failure, coordinator rationale, and Product Owner approval when materially more costly. Reasoning is never raised automatically.

No model fallback is silent. Record requested and available models plus risk before using only these upward substitutions: Luna → Terra medium; Terra medium → Terra high; Terra high → Sol high. If Sol high is required for security, Money, tenancy or normative analysis and unavailable, stop that work package.

Spawn only for a bounded independent analysis, a separable write set, real parallelism, proportionate independent review, or context reduction. Do not spawn when delegation costs more than the task, repeats resolved work, needs unbounded repository context, overlaps another writer, repeats a completed review, or is purely administrative. Every spawn receives a context packet containing work-package objective, branch/verified HEAD, task/requirement/invariant IDs, decisions, required reads, allowed and forbidden paths, exact command, expected result, known risks and report format.

Future implementation allows at most two concurrent writers with completely disjoint write sets and one additional read-only reviewer. Composer files, the frontend lock, providers, routes, shared middleware/fixtures, rollback map, permission catalogue, task registries and Spec Kit artifacts each have at most one writer at a time. The coordinator alone changes task checkboxes and normative registries after diff review and the exact registered validation.

One independent review is the default. Sol high review is mandatory for Money, tenancy, authorization, security, destructive operations, revision restore, contract-generation idempotency and economic-dataset parity. Ordinary UI uses Terra medium implementation, coordinator review and focused React/browser tests. Sol medium visual review is reserved for the first reference screen, Budget, the operational global layout or material interaction-design problems. A second independent reviewer is allowed only after a material first-review finding and for a precise critical remediation.

A task may pass through at most two implementation-review cycles. After two reopenings, stop, inspect and correct the requirement/plan/task source, run `/speckit.analyze`, and only then issue a new work package; never run a third implementation against the unchanged contract.

Context and token control are outcome-based: one objective and one visible result per work package; one to three tightly coupled tasks per implementation invocation; only necessary files; reuse current recorded research; no duplicate review without cause; medium reasoning for ordinary frontend; focused suite during a task, layer suite at checkpoints, full suite only at declared gates; stop on incomplete contracts; never modify tests merely to chase code; do not rerun global analysis after every file. Every spawn is appended to the orchestration ledger with timestamp, work-package ID, scope, chosen model/reasoning and rationale, mode, read/allowed/forbidden paths, commands/results, findings, coordinator decision and any relevant token-control decision without invented token counts.
