# Technical contract amendment — 2026-08-03

Status: `PARTIALLY SUPERSEDED — A-TECH-001/002 REMAIN CURRENT; A-TECH-003 IS HISTORICAL`
Authority at approval: Constitution 3.0.1, approved runtime matrix and then-current `/speckit.analyze` findings
Product impact: none

This document corrects technical contradictions without changing Q-001–Q-041.

## A-TECH-001 — Package compatibility target

The package-validation item in `versioning-permissions-and-operations-contract.md` that says “PHP 8.5” is superseded.

The authoritative compatibility target is:

- PHP `8.3.32` through Composer `config.platform.php`;
- Laravel `13.22.0`;
- Filament `5.7.3`;
- MySQL `8.4.10`.

A package that resolves only on PHP 8.4/8.5 is incompatible with the approved launch runtime unless a later technical amendment changes the platform target and records hosting, migration and test impacts.

## A-TECH-002 — Conditional backup dependency gate

`spatie/laravel-backup` remains conditionally approved at `10.3.0`.

The executable gate is the exact guarded command in `docs/replatform/task-execution/feature-006.md` for T006-012. It must:

1. run with Composer platform PHP `8.3.32`;
2. copy `composer.json` and `composer.lock` before resolution;
3. run `composer require spatie/laravel-backup:10.3.0 --with-all-dependencies --no-interaction`;
4. restore both Composer files and exit non-zero when dependency resolution fails;
5. retain the resolved files only after successful resolution;
6. run the package smoke/contract tests;
7. stop Feature 006 backup implementation and open a technical amendment if the gate cannot pass;
8. never downgrade, use `--ignore-platform-reqs`, install a fallback package or create a hidden custom backup mechanism.

## A-TECH-003 — Historical Spec Kit gate

The 3.0.1 gate and its former final-pass claim are historical. Constitution 5.0.0 and the 2026-08-04 product clarifications superseded that readiness result. Current status is owned by `implementation-readiness.md`, `artifact-status-register.md`, and the latest dated integrated analysis report; this amendment does not authorize `/speckit.implement` by itself.

A-TECH-001 and A-TECH-002 remain normative until superseded by a later explicit technical amendment or successful locked dependency evidence.
