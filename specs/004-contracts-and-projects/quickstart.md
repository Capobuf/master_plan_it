# Verification quickstart — Feature 004 Contracts and projects

Future commands; none were executed during planning.

## Fixture

Create two tenants. In tenant A create all project stages, a deferred target year, one project with a linked current Expense, one contract with multiple non-overlapping terms, one system-managed ToConfirm generated Actual, one manually modified occurrence, one confirmed occurrence and one suppressed occurrence. Verify no tenant role can receive `deletion-reason-setting.manage`; as global Administrator toggle only explicitly selected tenant A and cover both optional and required modes.

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
10. Reject project deletion while its current Expense exists; delete that Expense explicitly, delete the project without cascade, then verify UI, Action, revision restore and import cannot reactivate it.
11. Delete a contract with generated Expenses and verify they remain current/user-authoritative with immutable contract/date/reason provenance and unchanged source keys; verify no future generation and no reactivation path.
12. Delete a used term and verify identical no-cascade/provenance/no-future-generation behavior plus denial of restoration of that stable term identity through revision restore/import.
13. Toggle the tenant reason setting, test project/contract/term blank and 500/501-character boundaries, cross-tenant denial and unchanged prior evidence.
14. Restore a revision of a current contract and verify generated history/source keys remain unchanged; reject restore for a deleted contract or a snapshot that would restore a terminal term identity.

Focused Dusk only for term editor or regeneration modal/history if browser-only behavior remains:

```bash
./vendor/bin/sail artisan dusk --filter=ContractEditorTest
```

## Cleanup

Use transactions or run-ID targeted deletion. Never reset/truncate the persistent test DB.

## Success

No duplicate source key, silent overwrite, hidden regeneration, cross-tenant occurrence or queued-worker dependency exists.
