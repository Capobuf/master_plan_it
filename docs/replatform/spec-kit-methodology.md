# Historical manual Spec Kit methodology

Status: `DEPRECATED — HISTORICAL METHOD RECORD`
Superseded by the installed official Spec Kit integration documented in `spec-kit-installation-2026-08-04.md` and by the current analyze/remediation cycle.

The original package described below was structured according to the methodology and conceptual templates of GitHub Spec Kit; that historical package did not use the Spec Kit CLI or Codex CLI. This statement does not describe the current repository integration.

## Replicated phases

1. **Constitution** — `.specify/memory/constitution.md`.
2. **Specify** — each `specs/*/spec.md`.
3. **Clarify** — the `Clarifications` section in every specification and the central `open-questions.md`.
4. **Checklist** — feature-specific files under `checklists/`.
5. **Plan** — each `plan.md`, supported by `research.md`, `data-model.md` and `contracts/`.
6. **Tasks** — each `tasks.md`.
7. **Analyze** — `docs/replatform/spec-kit-analysis.md`.

The Implement phase was not performed.

## Constitution check

Each plan records a pre-architecture and post-architecture assessment. A deviation is allowed only when it identifies:
- the requirement;
- the evidence;
- why standard Laravel is insufficient;
- operational cost;
- a revisit condition.

## Clarification handling

Repository-verifiable questions are resolved from source evidence. External framework questions are resolved from official documentation. Remaining questions are classified as blocking or non-blocking and do not stop unrelated documentation.

## Remote-only limits

The analysis did not execute Frappe, tests, migrations, containers or database queries. Runtime-only behavior, production data quality, actual dataset size, hosting provider constraints and generated report fidelity remain validation gates. Statements affected by these limits are labelled `OPEN QUESTION`, `INFERRED` or `PROPOSED`.
