# Verification quickstart — Feature 004 Contracts and projects

Future commands; none were executed during planning.

## Fixture

Create two tenants. In tenant A create all project stages, a deferred target year, one contract with non-overlapping terms, one system-managed ToConfirm generated Actual, one manually modified occurrence, one confirmed occurrence and one suppressed occurrence.

## Focused tests

```bash
./vendor/bin/sail artisan test --filter=ProjectStage
./vendor/bin/sail artisan test --filter=ContractTerm
./vendor/bin/sail artisan test --filter=ContractGeneration
./vendor/bin/sail artisan test --filter=GenerationSuppression
./vendor/bin/sail artisan test --filter=ContractNotification
```

## Acceptance path

1. Reject overlapping terms and other-tenant references.
2. Run synchronization twice and verify one source occurrence.
3. Change a system-managed term and verify only its unconfirmed occurrence updates.
4. Manually edit and confirm generated occurrences; verify later sync does not overwrite them.
5. Delete with regeneration allowed; synchronize and verify recreation.
6. Delete and suppress; verify synchronization skips it.
7. Resume and generate now; verify one occurrence.
8. Select an invalid/already-generated year and receive explicit error.
9. Change project stages and verify the later economic-kernel fixture keeps Actual primary.
10. Restore a contract revision and verify generated history/source keys remain unchanged.

Focused Dusk only for term editor or regeneration modal/history if browser-only behavior remains:

```bash
./vendor/bin/sail artisan dusk --filter=ContractEditorTest
```

## Cleanup

Use transactions or run-ID targeted deletion. Never reset/truncate the persistent test DB.

## Success

No duplicate source key, silent overwrite, hidden regeneration, cross-tenant occurrence or queued-worker dependency exists.
