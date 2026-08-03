# Local rules for coding agents

1. Read `.specify/memory/constitution.md`, the feature spec/plan/contracts/tasks and traced legacy files before editing.
2. The repository documentation is authoritative; do not replace it with memory or assumptions. Re-check official framework documentation when needed.
3. Implement only a Ready task. Do not redesign architecture, rename mapped files or add packages without an approved ADR amendment.
4. Write the exact mapped test first. Do not run the Frappe application to infer missing rules unless explicitly authorized in a separate task.
5. Never use float for authoritative money; never calculate economic values in JavaScript/Blade; never count contracts/projects independently.
6. Do not use observers for economic side effects. Use explicit Actions and documented transactions.
7. Keep comments in English and only for non-obvious reasoning. Remove ceremonial prose from code.
8. Stop and report a `CONFLICT` when repository evidence contradicts the documentation; do not silently choose.
