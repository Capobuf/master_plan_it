# How to manage contracts, renewals, and expiries

## Core idea
Contracts/subscriptions are managed in `MPIT Contract`.
`next_renewal_date` is the operational date used for renewals views and alerts.

## Native visibility (no custom UI)
- List view with saved filters (e.g. renewals in 30/60/90 days)
- Calendar view using `next_renewal_date`
- Kanban view by `status`
- Report: Renewals window (today..today+N)

## Recommended fields
- vendor, category, title
- start/end dates (if applicable)
- next_renewal_date (mandatory)
- auto_renew
- current_amount (uses the site currency from MPIT Settings)

## Optional reminders (V1.1)
Use `scheduler_events` to create daily reminders (e.g. ToDo or email) for renewals approaching.
This is backend-only and remains within native Frappe capabilities.
