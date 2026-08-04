# GitHub Actions one-shot workflow investigation — 2026-08-04

Status: `VERIFIED CURRENT — DISPOSITION COMPLETE`

Authoritative branch: `laravel-replatform`

Classification: `D — DUPLICATED OR OBSOLETE`

## Scope

The repository has three historical GitHub Actions records. Each workflow was a temporary, self-mutating documentation migration triggered only when its own workflow file was pushed to `laravel-replatform`. None is a Laravel, Frappe, test, build, release or deployment gate.

| Workflow ID | Name | Historical file | Relevant run | Result |
|---:|---|---|---:|---|
| 325976613 | Apply clarifications Q-016 through Q-020 | `.github/workflows/apply-clarifications-q016-q020.yml` | 30788918320 | failure |
| 325954420 | Apply Spec Kit update | `.github/workflows/apply-spec-kit.yml` | 30786207240 | success |
| 325956838 | Reconcile Spec Kit clarifications | `.github/workflows/reconcile-spec-kit.yml` | 30786552632 | success |

## Failed run evidence

- run: `30788918320`;
- event: `push`;
- branch: `laravel-replatform`;
- source commit: `cc8ce61508d07a580d1a0f975a9f750fee85f35e`;
- job: `apply` (`91608196080`);
- failed step: `Validate clarified Spec Kit`;
- original exit code: `1`;
- first and last failure: the same run, because no later run exists;
- workflow introduction: commit `cc8ce61508d07a580d1a0f975a9f750fee85f35e`;
- workflow removal: commits `af66876343db5d50b3107aa2c4d646ea7785980b` and the authoritative PR #3 squash `e19b5dae0bccef816ec8fcc08a2dcee3c1e29490`.

The GitHub job log stops at this assertion:

```bash
test -z "$(grep -RIl --include='*.md' \
  'Q-016 through Q-024 remain open' . || true)"
```

An isolated reproduction on the run commit, after executing the workflow's three mutation steps, returned:

```text
+ grep -RIl '--include=*.md' 'Q-016 through Q-024 remain open' .
+ test -z ./docs/replatform/spec-kit-analysis.md
```

The root cause was a stale statement in `docs/replatform/spec-kit-analysis.md` that the one-shot mutation did not update. This was a real validation failure, not a runner, secret, permission, quota or transient dependency failure.

## Branch and protection relationship

The historical trigger was restricted to `laravel-replatform` and to a push modifying the workflow file itself. The workflow file is absent from current `main`, `develop`, `refactor/reports`, `check-test`, `laravel-replatform`, PR #9 provenance and every other fetched replatform branch inspected on 2026-08-04.

GitHub reports no classic branch protection and no repository rulesets for `main` or `laravel-replatform`; therefore none of the three workflow contexts is a required check. The legacy Frappe branches do not consume these documentation migrations.

## Disposition

The failed workflow is obsolete rather than a CI gate that should be repaired:

1. its approved decisions are already preserved by Constitution 3.0.1 and Q-001–Q-041;
2. its workflow file was already removed through the replatform PR history;
3. reintroducing it would replay a superseded product-state mutation;
4. making the assertion pass would provide no current quality guarantee;
5. no Laravel application exists yet, so no application CI is claimed.

The two successful one-shot workflows have the same obsolete lifecycle. Their files self-removed after use. On 2026-08-04 all three residual GitHub Actions records were changed from `active` to `disabled_manually`:

- workflow `325976613` at `2026-08-04T06:36:10+02:00`;
- workflow `325954420` at `2026-08-04T06:36:11+02:00`;
- workflow `325956838` at `2026-08-04T06:36:11+02:00`.

No replacement run ID exists by design: no runnable workflow file remains, and a synthetic run would not verify current documentation. Future Laravel CI is owned by implementation tasks T001-022/T001-023 and remains blocked until the Spec Kit documentation gate passes.

## Verification

- full repository Actions inventory: 3 historical runs, exactly one failure;
- failing job log fetched from the job-log API because `gh run view --log-failed` returned no text;
- failure reproduced in a detached temporary worktree at the exact run SHA;
- historical workflow definitions read at the exact run SHAs;
- relevant branch workflow trees inspected without switching the primary worktree;
- branch protection and ruleset APIs checked;
- post-disposition `gh workflow list --all` reports all three records as `disabled_manually`;
- no workflow file, application code or application dependency was added.

## Residual limits

The historical red run remains immutable evidence in GitHub Actions and is not rerun or deleted. Application tests, builds and deployment checks do not exist yet and are not represented as successful.
