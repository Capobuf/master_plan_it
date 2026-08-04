# Contract — Hosting

Feature: `001-platform-foundation`  
Status: `IMPLEMENTATION CONTRACT — PACKAGE LOCK AND HOST PROFILE VERIFICATION REMAIN OPEN GATES`
Purpose: shared-hosting prerequisites, runtime/database compatibility, document root, immutable build artifact, cron, storage and release handoff.

## Runtime boundary

- Laravel 13 modular monolith.
- PHP 8.3.32 and the package versions in the Feature 001 plan are planned constraints; they become authoritative runtime evidence only after T001-001 resolves the lock and T001-003 completes the Sail smoke check.
- MySQL 8.4 LTS is the minimum accepted database family; an additional current family is supported only after the approved compatibility spike.
- InnoDB, `utf8mb4` and strict SQL mode are mandatory.
- Runtime may not require Redis, WebSockets, Node.js, a permanent queue worker or a second application service.

A hosting profile is not accepted until its actual PHP runtime/extensions, database family/mode, document root, cron, writable paths, ZIP extraction, command execution and rollback capabilities are verified. No silent compatibility fallback is permitted.

## Build and artifact contract

The production artifact is created by GitHub Actions only from the exact commit that passed every required quality gate in `development-and-test-contract.md`.

The artifact contains:

- production Composer dependencies resolved from the lock file;
- compiled Vite assets and valid manifest;
- application source and required public assets;
- source commit/version and package checksum metadata.

It excludes:

- production secrets and local environment files;
- test databases or test fixtures not required at runtime;
- caches generated for another environment;
- Node runtime dependencies;
- development-only dependencies and tooling.

Hosting deploys the artifact unchanged. It does not run `npm install`, rebuild Vite assets, or select different dependency versions. Feature 006 owns upload/extraction, release activation, backup, health checks and rollback.

## Application configuration

Configuration is environment-owned and is never embedded in the release ZIP. The hosting profile supplies at least:

- application key and URL;
- database credentials;
- mail configuration when enabled;
- filesystem/storage paths;
- scheduler invocation;
- any verified dump/restore binary path required by the backup contract.

The web document root points to Laravel `public/`. Application source, storage internals, environment files and private tenant files must not be web-accessible.

Writable paths are limited to Laravel-required cache/storage locations and approved tenant file storage. Permission failures are blocking and diagnostic; the application must not broaden permissions silently.

## Database and migrations

Deployment applies forward migrations through the approved Feature 006 release procedure.

Production and hosting validation never invoke:

- `migrate:fresh`;
- `db:wipe`;
- unscoped truncation;
- destructive database reset.

Every release-eligible database family must pass migrations, the complete accounting layer and representative application smoke tests before packaging.

## Scheduler and process model

One cron entry invokes Laravel scheduler every minute. Scheduled operations are bounded, use overlap prevention where required, resolve tenant/platform scope explicitly and expose failure.

Queue connection defaults to `sync`. No permanent process is required at launch.

## Tenant and authorization boundary

Hosting serves one multi-tenant Laravel application. No custom tenant domain or separate tenant database is required.

Hosting configuration cannot bypass tenant context, policies, configurable permissions, revision boundaries or economic invariants. Files remain tenant-owned and are served only through authorized application paths.

## Acceptance tests

1. extracted artifact contains a valid Vite manifest and every referenced compiled asset;
2. application boots from the artifact without Node.js or a permanent worker;
3. production caches build from the deployed artifact;
4. health smoke validates runtime/extensions and database connectivity without exposing secrets;
5. scheduler registration is present and does not require a daemon;
6. document-root checks prevent direct access outside `public/`;
7. failed required quality jobs cannot publish a release artifact;
8. artifact records the exact verified source commit and is reused unchanged by deployment;
9. production migration path is forward-only and contains no reset command;
10. hosting profile and rollback remain consistent with Feature 006.

## Errors

- unsupported PHP, extension or database profile: fail preflight with exact requirements;
- missing compiled asset/manifest: fail packaging before publication;
- unwritable required path: fail preflight without permission broadening;
- missing cron: profile is not operationally accepted;
- migration failure: stop deployment and invoke the Feature 006 recovery path;
- unexpected failure: expose correlation ID and sanitized diagnostics without credentials or tenant payloads.
