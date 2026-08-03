# Contract — Deployment

Feature: `006-data-migration-and-operations`  
Status: `PLANNING INPUT — REAL HOST PROFILE REQUIRED`  
Purpose: immutable release ZIP, verified hosting profile, health checks, forward migration, activation and rollback.

## Inputs

Deployment receives:

- one immutable release artifact produced from a known commit after all required GitHub Actions quality gates pass;
- external environment configuration and secrets;
- a verified hosting profile documenting PHP runtime/extensions, database family/mode, document root, cron, writable paths, upload/extraction method, command access and rollback capability;
- a verified pre-deployment backup according to the backup/restore contract.

No transport, filesystem path or hosting capability may be invented before the real profile is inspected.

## Runtime boundary

- Laravel 13 on the exact runtime locked by `/speckit.plan`;
- MySQL 8.4 LTS minimum, plus only additional families proven by the compatibility matrix;
- InnoDB, `utf8mb4`, strict SQL mode;
- one Laravel application, approved filesystem storage and cron;
- no required Node.js runtime, Redis, WebSockets, permanent queue worker or second application service.

Unsupported or unknown profiles fail preflight. There is no undocumented compatibility fallback.

## Artifact contract

The release ZIP:

- is created from the exact commit that passed required static, accounting, application, relevant browser and database-compatibility checks;
- contains production Composer dependencies and compiled Vite assets;
- contains source commit/version and checksum metadata;
- excludes secrets, local environment files, test databases, caches generated for another environment, development dependencies and Node runtime dependencies;
- is deployed unchanged; hosting does not rebuild dependencies or frontend assets.

The artifact may include build provenance/attestation when enabled by the repository plan. An Actions artifact is a handoff mechanism, not a substitute for release checksums, backup or restore verification.

## Deployment sequence

1. identify the exact artifact, source commit and checksums;
2. verify host preflight and available rollback target;
3. create and verify the required pre-deployment backup state;
4. enter the approved maintenance state;
5. extract the artifact into a new release location or the verified hosting equivalent;
6. attach environment configuration and shared writable paths;
7. run forward-only production migrations;
8. rebuild Laravel caches from the deployed artifact;
9. run health, authentication, tenant-isolation, scheduler and representative accounting smoke checks;
10. activate the release atomically where supported, otherwise use the explicit verified replacement sequence;
11. exit maintenance mode only after acceptance;
12. record deployment metadata and retain the prior release until acceptance completes.

The exact shell, SFTP, CloudPanel, cPanel or hosting-panel procedure is selected only after real environment verification.

## Database and rollback rules

Production deployment never executes:

- `migrate:fresh`;
- `db:wipe`;
- unscoped truncation;
- destructive schema reset.

Migration failure stops deployment and remains diagnosable. No step continues silently.

A code rollback is not represented as sufficient when forward migrations changed schema or persisted data incompatibly. The rollback plan identifies separately:

- previous immutable artifact activation;
- database recovery or forward corrective migration;
- writable/shared file consistency;
- maintenance and routing restoration;
- post-rollback smoke and reconciliation.

Backup/restore remains installation-wide. Tenant portability is not a deployment rollback mechanism.

## Tenant and authorization clauses

Deployment hosts one multi-tenant Laravel application. Health and smoke checks use explicit tenant context and verify same-tenant success plus safe cross-tenant denial.

Checks and logs must not expose credentials, hashes, attachment payloads, audit payloads or another tenant's existence/data.

## Acceptance tests

1. release cannot be created from a failed required quality run;
2. package-content test verifies required and prohibited files;
3. extracted artifact boots without Node.js or a permanent worker;
4. forward migrations and complete accounting tests pass for every supported database family before release eligibility;
5. health check validates runtime, database, caches, writable storage and scheduler without leaking secrets;
6. representative accounting smoke returns exact approved decimal values;
7. same-tenant access succeeds and other-tenant access fails safely;
8. rollback rehearsal identifies code, database and shared-file recovery steps;
9. deployment records artifact identifier, commit, checksums, start/end, actor and result;
10. no test or deployment command resets persistent development, test or production databases implicitly;
11. failed backup, migration, smoke or activation blocks acceptance and retains the previous release.

## Audit and errors

Record deployment actor, artifact, source commit, hosting profile, database family, migration result, smoke result, activation and rollback decision. Do not log secrets, environment files, dumps or sensitive tenant payloads.

Every failure returns a stable operation result and correlation ID. Silent retry or silent partial success is prohibited.
