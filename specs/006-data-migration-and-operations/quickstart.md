# Verification quickstart — Feature 006 Migration and operations

Future commands; none were executed during planning.

## Import fixture

Build a small versioned package with two tenants' source-like rows but select only tenant A target. Include valid rows, duplicate lineage replay, collision, unassignable reference, approved exclusion, attachments, legacy replacement history, scenarios and BudgetVersion snapshots. Include prohibited audit/password fields to verify rejection/exclusion.

## Focused tests

```bash
./vendor/bin/sail artisan test --filter=ImportManifest
./vendor/bin/sail artisan test --filter=ImportDryRun
./vendor/bin/sail artisan test --filter=ImportApply
./vendor/bin/sail artisan test --filter=TenantPortability
./vendor/bin/sail artisan test --filter=BackupOperation
./vendor/bin/sail artisan test --filter=DeploymentArtifact
```

## Acceptance path

1. Stage package and verify checksums/source locations.
2. Dry-run: confirm zero current-domain writes and explicit quarantine.
3. Attempt apply with blocker and receive `IMPORT_BLOCKERS_PRESENT`.
4. Approve an exclusion with reason; apply with reinforced confirmation.
5. Replay same lineage and verify idempotency.
6. Import a conflicting lineage and verify no overwrite/rename/merge.
7. Export tenant package and inspect absence of audit, secrets/global settings/other tenant.
8. Round-trip approved data with exact decimals/checksums.
9. Resolve backup package on PHP 8.3.32, create archive and record Created.
10. Restore in disposable empty environment and mark Verified only after smoke/reconciliation.
11. Build release artifact and verify source commit, Vite manifest, required/prohibited content.

## Commands

```bash
./vendor/bin/sail artisan mpit:import-dry-run <package> --tenant=<id>
./vendor/bin/sail artisan mpit:import-apply <run-id>
./vendor/bin/sail artisan mpit:backup
./vendor/bin/sail artisan mpit:backup-verify <run-id>
```

Final names/options must match implemented command contracts from tasks.

## Cleanup

Disposable restore environment may be destroyed explicitly. Persistent test DB uses run-ID targeted cleanup only; no reset/truncate.

## Success

No guessed tenant, silent collision, audit/secret export, unreported partial apply, unverified backup acceptance or production rebuild exists.
