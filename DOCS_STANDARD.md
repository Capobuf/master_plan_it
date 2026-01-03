---
type: meta
updated: 2026-01-03
---

# Documentation Standard

Standard per documentazione ottimizzata per agent AI in progetti vibe coding.

## Core Rules

1. **Bullet max 80 char** — spezza in sub-bullet se necessario
2. **Struttura 3-tier** — TL;DR (5 righe max) → Details → Deep-dive (opzionale)
3. **Tabelle per dati strutturati** — field definitions, states, mappings
4. **Code in fenced blocks** — mai inline multi-linea
5. **Cross-ref espliciti** — usa `→ see [doc]` invece di duplicare
6. **YAML frontmatter** — `type`, `updated` su ogni doc

## Doc Types

| Type | Purpose | Max Lines | Template |
|------|---------|-----------|----------|
| ADR | Architectural decision | 25 | Status → Context → Decision → Consequences |
| Reference | Technical specs | 150 | TL;DR → Tables → Details |
| How-to | Step-by-step procedure | 50 | Prerequisites → Steps → Troubleshooting |
| Agent Instructions | Rules for LLM agents | 60 | Non-negotiables → Workflow → Cross-refs |

## YAML Frontmatter

```yaml
---
type: reference|how-to|adr|agent-instructions
updated: YYYY-MM-DD
---
```

## Templates

### ADR

```markdown
# ADR NNNN: [Title]

Date: YYYY-MM-DD

## Status
Proposed|Accepted|Deprecated|Superseded

## Context
[1-3 sentences]

## Decision
[1-3 sentences]

## Consequences
- Pro 1
- Con 1
```

### Reference

```markdown
---
type: reference
updated: YYYY-MM-DD
---

# Reference: [Topic]

## TL;DR

- Key fact 1
- Key fact 2

## Details

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| ... | ... | ... | ... |
```

### How-to

```markdown
---
type: how-to
updated: YYYY-MM-DD
---

# How-to: [Action]

## Prerequisites

- Item 1
- Item 2

## Steps

1. **Action** — `command`
2. **Verify** — expected output

## Troubleshooting

→ see [related doc]
```

## Anti-patterns

- ❌ Dense paragraphs (200+ chars single line)
- ❌ Duplicated content across docs
- ❌ Mixed policy/procedure/rationale in same section
- ❌ Inline multi-line code blocks disrupting flow
- ❌ Explanatory prose where imperative steps suffice
