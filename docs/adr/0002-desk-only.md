# ADR 0002: Clients use Desk (System Users), no Portal/Website Users

Date: 2025-12-21

## Status
Accepted

## Context
We require dashboards, analytics, free exploration (lists/filters), workflow approvals, and native UI without custom frontend code.

## Decision
Clients are System Users and access the app via Desk. Portal/Website Users are not used.

## Consequences
- Full native features available (reports/dashboards/workflows).
- Client UI looks like an admin app, but can be constrained by roles/workspaces.
- No custom portal pages are needed (aligns with 'no JS/CSS custom').

