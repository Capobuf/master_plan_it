# Master Plan IT (MPIT) — Documentation

Version: 0.1 (V1 blueprint + EPIC E01 ✅)  
Last updated: 2025-12-22

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

## Recent Enhancements (EPIC MPIT-E01) ✅
**Completed:** Dec 22, 2025

### Phase 1-6 Implemented:
1. **User Preferences** — Per-user VAT defaults, naming series, print options
2. **Title Field UX** — Human-readable titles in link fields (Budget, Project)
3. **Naming Automation** — Deterministic naming: `BUD-2025-01`, `PRJ-0001` based on Year
4. **Strict VAT Normalization** — All Currency fields split into `net/vat/gross` triple with validation
5. **Annualization** — Temporal calculations for Monthly/Quarterly/Annual/Custom/None recurrence patterns
6. **Professional Printing** — Jinja print formats for Budget/Project + HTML templates for 4 Query Reports

### Key Features:
- ✅ VAT calculation with user defaults and strict validation (Phase 4)
- ✅ Annualization rules with Rule A enforcement (zero overlap blocked) (Phase 5)
- ✅ Professional print formats (server-side Jinja, no custom frontend) (Phase 6)
- ✅ Report HTML templates with color-coded variance and status badges (Phase 6)
- ✅ Dual-mode controller supporting legacy `amount` and new `amount_net` flows
- ✅ All metadata versionable in git (zero drift)
- ✅ Full test coverage: verify.run + run-tests passing

See: `docs/how-to/10-epic-e01-money-naming-printing.md` for complete implementation guide.

## Constraints
- **Desk only**: clients are **System Users** (no portal / Website Users).
- **Native Frappe components only** (no custom JS/CSS, no asset build).
- **ADR 0006:** No custom frontend/scheduler (server-side rendering only)
- **ADR 0008:** Print formats via Jinja templates (versionable in repo)

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
