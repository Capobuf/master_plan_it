# Spec Kit installation and repository integration — 2026-08-04

Status: `VERIFIED CURRENT — UPSTREAM INTEGRATION INSTALLED`

Authoritative base: `laravel-replatform` commit `3dd3c93078bd49e5662e24024dc02d1ae40d2be0`

## Official release

- repository: `github/spec-kit`;
- latest stable release at installation time: `v0.15.2`;
- release published: `2026-08-03T19:07:13Z`;
- draft: no;
- prerelease: no;
- annotated tag object: `57e5bcd3bf1aae12a4a082bfa78ad6fb39cd1dd7`;
- release commit: `a0687f4b46636631fb8eb0822a28ee85e24b9329`;
- CLI-reported version: `0.15.2`;
- upstream Python requirement: `>=3.11`;
- installed runtime: Python `3.12.3` on Linux x86_64.

The v0.15.2 release notes declare fixes and additive integration/extension behavior; they do not declare a breaking change. The repository previously contained only the local Constitution and had no versioned Spec Kit integration manifest, scripts, templates, workflow or agent commands.

## Installation

- `uv`: `0.12.1`, installed with the official Astral standalone installer;
- executable: `/root/.local/bin/specify`;
- exact command:

```bash
uv tool install specify-cli \
  --from "git+https://github.com/github/spec-kit.git@v0.15.2"
```

`specify version`, `specify --help`, `specify check` and `specify self check` were executed. The CLI reported `Up to date: 0.15.2`.

## Agent integration

`specify check` detected `Codex CLI (available)`. No agent directory or prior integration manifest existed. The selected integration is therefore the verified `codex` integration.

The repository update command was:

```bash
specify init --here --force --integration codex
```

Spec Kit v0.15.2 installs Codex skills under `.agents/skills/`. Upstream documents this integration as skills mode: the repository's former `/speckit.*` terminology maps to `$speckit-*` for Codex. This naming change does not alter the approved product artifacts or authorize implementation.

## Upstream files installed

- ten Codex skill definitions under `.agents/skills/`;
- `.specify/init-options.json`;
- integration state and Codex/Spec Kit manifests under `.specify/integrations/`;
- five shell scripts under `.specify/scripts/bash/`;
- five templates under `.specify/templates/`;
- bundled `speckit` workflow and workflow registry under `.specify/workflows/`.

The bundled workflow contains an `implement` step and was not run. This engagement continues to prohibit `/speckit.implement` and `$speckit-implement`.

## Preserved local artifacts

`.specify/memory/constitution.md` was preserved byte-for-byte. Its SHA-256 before and after initialization is:

```text
34f1434c86be811790f012c13ee712cb18084f2bff5f7dc6ab600bef3390f6d1
```

No decision, clarification, contract, feature artifact, task registry or analyze report was overwritten. Q-001–Q-041 remain closed and unchanged by this integration update.

## Validation

- `specify integration status`: `OK`, default `codex`, 0 modified/missing/invalid managed files;
- `specify workflow list`: bundled `speckit` workflow v1.0.0 detected;
- `specify workflow info speckit`: six-step graph inspected but not run;
- `specify workflow resolve speckit`: only the bundled base layer is active;
- every installed JSON file parsed successfully;
- the installed YAML workflow parsed successfully;
- every installed Bash script passed `bash -n`;
- Constitution hash unchanged;
- no application dependency, scaffold, code, migration, test, build or deployment was added.

The CLI has no `specify workflow validate` command in v0.15.2; validation used only commands present in the installed help plus direct syntax parsing.
