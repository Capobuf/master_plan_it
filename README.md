# Master Plan IT (MPIT) — Documentation

Version: 0.1 (V1 blueprint)  
Last updated: 2025-12-21

This documentation is written in **Diátaxis** format (Tutorials, How‑to Guides, Reference, Explanation) plus **ADR** (Architecture Decision Records).

## What this is
Master Plan IT is a **Frappe Desk** application for vCIO budgeting, contracts/renewals and project governance.

## Scope (V1)
- Baseline historical spend (importable) with comments and validation states
- Annual budget proposal and approval (approved baseline is immutable)
- Budget amendments (post-approval deltas)
- Actuals (consuntivi) entry with warnings (no blocking)
- Vendors and Contracts/Subscriptions with renewals visibility
- Projects with **mandatory multi-year allocations**
- Workflow approvals for Budgets and Budget Amendments (vCIO Manager / Client Editor)
- Reports & dashboards for:
  - Approved vs Actual
  - Current (Approved + Amendments) vs Actual
  - Renewals / expiries window
  - Projects planned vs actual

## Constraints
- **Desk only**: clients are **System Users** (no portal / Website Users).
- **Native Frappe components only** (no custom JS/CSS, no asset build).

## Quick links
- Tutorials: `docs/tutorials/`
- How‑to: `docs/how-to/`
- Reference: `docs/reference/`
- Explanation: `docs/explanation/`
- ADR: `docs/adr/`
- User guide: `docs/how-to/08-user-guide.md`
- Docker notes: `docs/how-to/09-docker-compose-notes.md`

## For agents (vibe coding)
Read `AGENT_INSTRUCTIONS.md` first. The project is intentionally structured so that:
- metadata is versioned on filesystem
- applying changes is one command (`bench migrate`)
- tenant provisioning is one idempotent script (`bootstrap.run`)
