# Technical contract amendment — 2026-08-03

Status: `APPROVED TECHNICAL CORRECTION`  
Authority: Constitution 3.0.1, approved runtime matrix and `/speckit.analyze` findings  
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

## A-TECH-003 — Current Spec Kit gate

- `/speckit.clarify`: complete;
- `/speckit.plan`: complete and merged;
- initial `/speckit.tasks`: complete and merged;
- initial `/speckit.analyze`: recorded 2 CRITICAL, 10 HIGH and 4 MEDIUM findings;
- first remediation: merged;
- rerun `/speckit.analyze`: recorded 0 CRITICAL, 8 HIGH and 4 MEDIUM findings;
- second `/speckit.tasks` remediation: complete;
- Spec Kit 0.15.2 integration and 2026-08-04 task/checklist remediations: complete and merged;
- second 2026-08-04 rerun on `0d3a84c38e177f90e53f74bb84f78594ca00d32d`: recorded 0 CRITICAL, 1 HIGH and 1 MEDIUM finding;
- ability-contract `/speckit.plan` remediation: complete and merged;
- third analysis on `f673513c137e797d52bc5f3099ed3c1c10b4a129`: recorded 0 CRITICAL, 1 HIGH and 0 MEDIUM findings;
- complete cross-contract C-07/Q-016 propagation: complete and merged;
- fourth analysis on `348a557c58388ff917646dd6a02cb00cbdc1f513`: recorded 0 CRITICAL, 1 HIGH and 0 MEDIUM findings;
- invariant task/test ownership remediation: complete; integration state authoritative only in GitHub PR metadata; final analysis required;
- integration state: authoritative only in GitHub PR metadata;
- next valid command on the latest integrated HEAD: `/speckit.analyze`;
- `/speckit.implement`: blocked until CRITICAL `0` and HIGH `0`.

This amendment is normative until the original contract is next regenerated in full.
