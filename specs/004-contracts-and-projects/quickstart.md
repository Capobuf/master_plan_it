# Verification quickstart — Contracts and projects

These are future commands; they were not executed during documentation deepening.

## Prerequisites

- Laravel environment installed from the locked dependency files.
- MySQL test database with strict mode.
- `.env.testing` uses Europe/Rome display configuration and EUR.
- Features before 004 migrated and seeded.

## Minimal data

Create one user for each role and the minimum records required for project register/editor, contract register/editor with term timeline, sync status. Use factories, not production data.

## Verification path

1. Run migrations and the feature seed fixture.
2. Authenticate as Administrator.
3. Open the primary screen: `project register/editor`.
4. Execute the main valid operation and record the expected persisted/result values from `spec.md`.
5. Repeat the mapped invalid, unauthorized, empty and stale-version scenarios.
6. Run `php artisan test --filter=Contractsandprojects` and the listed focused Dusk test only if the feature uses browser JavaScript.

## Success criteria

- All mapped FR/INV tests pass.
- No failed job/queue dependency exists.
- Database totals and screen values match the documented dataset.
- Logs contain no unexpected error or sensitive payload.

## Cleanup

Drop the disposable test database or run `migrate:fresh` only in the test environment. Never use cleanup commands against production.
