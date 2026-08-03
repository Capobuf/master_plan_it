# Master Plan IT — Laravel replatform Spec Kit

This branch is intentionally documentation-only. It contains no Frappe or Laravel application code.

Baseline:

- legacy repository: `Capobuf/master_plan_it`;
- legacy branch: `refactor/reports`;
- verified legacy commit: `e1f6dd2f770dbbdd0b5739ac7da4a575ec142bb3`;
- target: Laravel replatform with explicit multi-tenant isolation.

Read in this order:

1. `.specify/memory/constitution.md`;
2. `docs/replatform/README.md`;
3. `docs/replatform/approved-decisions.md`;
4. `docs/replatform/product-clarification-register.md`;
5. `docs/replatform/clarification-log.md`;
6. `specs/007-tenancy-and-access-control/spec.md`;
7. feature specifications `001` through `006`.

The approved product decisions `Q-001` through `Q-015` are propagated into the package. All remaining open questions are listed in `docs/replatform/product-clarification-register.md` and must not be guessed by an implementation agent.
