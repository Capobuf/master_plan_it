# ADR 0006: No custom frontend; optional backend scheduler for reminders

Date: 2025-12-21

## Status
Accepted

## Context
The project forbids custom JS/CSS and asset builds. We still want renewal reminders.

## Decision
Use native Desk views/reports for visibility. Optionally add backend reminders via Frappe scheduler_events (daily job) to create ToDos or send emails.

## Consequences
- Core visibility works without automation.
- Reminders can be added later without violating constraints.
- Scheduling logic is backend-only and versionable.

