# Quickstart verification — Feature 010 Projects

## Prerequisites

- checkout `feature/010-projects` from the current `laravel-replatform` baseline;
- existing Composer and frontend lockfile dependencies installed;
- test MySQL reachable using the guarded settings in `docs/OPERATIONS.md`;
- an active Tenant with Planning Years, Cost Centers and an authorized actor.

## Automated verification

From repository root:

```bash
composer test:static
composer test:prepare
composer test:accounting
composer test:application
composer verify
php artisan route:list --path=api/v1/projects
php artisan route:list --path=api/v1/expenses
```

From `frontend/`:

```bash
npm ci
npm run lint
npm run build
```

These commands are forward-only for the persistent test database. Do not use `migrate:fresh`, `db:wipe`,
`RefreshDatabase` or `DatabaseTruncation`.

## End-to-end scenarios

1. Open Progetti, create an Approved Project, inspect its detail and edit it with the current lock version.
2. Create a Deferred Project: verify the target year appears and is required; run the promotion command
   before/at target and verify idempotency.
3. Create/edit an Expense and select the Project. Verify selecting Contract clears Project and vice versa;
   verify the detail/register link back to the Project.
4. Create Estimate/Quote/Actual rows across Project stages and compare Dashboard, Budget and Report:
   Primary/Proposed/Idea/Excluded/Potential must match the shared server response and Actual stays Primary.
5. Change Project stage and reload the three consumers: classification changes without modifying Expense.
6. Open Project revision history, compare a revision and restore it with the current lock version. Verify a
   new history item is added and the source remains unchanged.
7. Attempt Project delete with a current linked Expense: expect `PROJECT_HAS_LINKED_EXPENSES` and no detach.
   Delete the Expense using its normal flow, then delete Project with the reason prompt and verify it no
   longer appears or restores.
8. Repeat tenant-bound reads/writes with missing abilities, inactive actor/Tenant and foreign identifiers.

## Visual verification matrix

Inspect `/progetti`, `/progetti/nuovo`, `/progetti/:id`, `/progetti/:id/modifica`, `/spese/nuova`,
`/spese/:id/modifica`, `/budget`, `/report`, and `/` on desktop, tablet and mobile, including dark mode,
loading, empty, validation, 403/API error, stale update, blocked delete, long title and Deferred states.
