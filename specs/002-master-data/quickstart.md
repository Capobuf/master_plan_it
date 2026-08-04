# Verification quickstart — Feature 002 Master data

Future commands; none were executed during planning.

## Fixture

Create two active tenants. In tenant A create two distinct calendar years, a root/child/grandchild cost-center tree, active/inactive vendors and users with granular permissions. Add historical references to one vendor and cost center; add separate unreferenced vendor and leaf cost center deletion fixtures.

## Focused tests

```bash
./vendor/bin/sail artisan test --filter=PlanningYear
./vendor/bin/sail artisan test --filter=CostCenter
./vendor/bin/sail artisan test --filter=Vendor
./vendor/bin/sail artisan test --filter=MasterDataRevision
```

## Acceptance path

1. Create a calendar year, verify January 1/December 31 derivation, reject duplicate/non-calendar import and confirm no date editor, delete or revision controls exist.
2. Move a cost center and reject a cycle, other-tenant parent or level-four placement; verify sibling name/ID order.
3. Reject parent deactivation while an active descendant exists.
4. Deactivate vendor/cost center; verify history remains and new selectors exclude them.
5. Restore a prior vendor/cost-center revision and verify a new revision is created.
6. Remove a permission and verify direct URL denial.
7. Delete an unreferenced vendor and leafless cost center; reject referenced vendor/cost-center deletion and cost-center deletion with descendants.
8. Confirm no revision table row appears in ordinary master-data queries and no planning-year revision row is created.

## Cleanup

Use transaction rollback or delete only fixture rows identified by the test run. Never reset or truncate the persistent test database.

## Success

All tenant, uniqueness, lifecycle, tree, concurrency and restore tests pass with no cross-tenant disclosure or unexpected log.
