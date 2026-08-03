# Technical contract amendment — 2026-08-03

Status: `APPROVED TECHNICAL CORRECTION`  
Authority: Constitution 3.0.1, approved runtime matrix and `/speckit.analyze` findings ANALYZE-H-003/H-004/M-003  
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

The executable gate is:

```bash
composer require spatie/laravel-backup:10.3.0 \
  --with-all-dependencies \
  --no-interaction
```

Required behavior:

1. run with Composer platform PHP `8.3.32`;
2. verify the resulting `composer.json` and `composer.lock` diff contains only approved dependency changes;
3. run the package smoke/contract tests;
4. when resolution fails, restore both Composer files and record `DEPENDENCY_LOCK_FAILED`;
5. stop Feature 006 backup implementation and open a technical amendment;
6. do not downgrade, use `--ignore-platform-reqs`, install a fallback package or create a hidden custom backup mechanism.

## A-TECH-003 — Current Spec Kit gate

The planning gate in `versioning-permissions-and-operations-contract.md` is historical. Current state:

- `/speckit.clarify`: complete;
- `/speckit.plan`: complete and merged;
- `/speckit.tasks`: remediation in review;
- `/speckit.analyze`: failed on baseline `54bc8c5c72167b47eedc7ae9cc62211310035f8c` and must be re-run after remediation;
- `/speckit.implement`: blocked until CRITICAL `0` and HIGH `0`.

This amendment is normative until the original contract is next regenerated in full.