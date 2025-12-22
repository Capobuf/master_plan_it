# ADR 0004: Projects can span years; per-year allocations are mandatory before approval

Date: 2025-12-21

## Status
Accepted

## Context
Projects may start in one year and finish in another. Annual reporting becomes ambiguous unless planned amounts are allocated per year.

## Decision
Require project allocation rows (year, planned_amount) before a project can be approved.

## Consequences
- Annual reports remain consistent.
- Slightly more data entry upfront.
- Prevents hidden debt from 'everything belongs to the start year' heuristics.

