# ADR 0003: Approved budgets are immutable; changes are modeled as delta amendments

Date: 2025-12-21

## Status
Superseded by ADR 0011 (Budget Engine V2)

## Context
We must compare 'budget defined at start of year' vs 'actual spent' consistently over time. Allowing edits to an approved budget would destroy the baseline and make reporting ambiguous.

## Decision
Budgets are approved via workflow and submitted (docstatus=1), making them immutable. Post-approval changes are done via Budget Amendments containing delta lines (+/-).

## Consequences
- Two stable comparisons: Approved vs Actual; Current vs Actual.
- Clean audit trail of changes.
- Requires amendment workflow discipline (but avoids technical debt).
