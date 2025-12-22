# ADR 0005: Contracts/Subscriptions are first-class objects for renewals visibility

Date: 2025-12-21

## Status
Accepted

## Context
Baseline imports are raw and may contain unclear/duplicated lines. Renewals/scadenze require a curated durable record to drive views and reminders.

## Decision
Introduce MPIT Contract as a dedicated DocType with explicit next_renewal_date and vendor/category linkage. Baseline expenses may link to a contract but are not required.

## Consequences
- Renewals views and reports are reliable.
- Contracts can be linked to budgets and actuals.
- Some manual curation required (but matches governance workflow).

