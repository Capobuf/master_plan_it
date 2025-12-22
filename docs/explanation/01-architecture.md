# Explanation: Architecture

## Tenant model
One client equals one Frappe site. This yields hard data segregation and simplifies permissions.
The vCIO works across many sites.

## Why Desk users for clients
Desk provides the full native experience: lists, forms, reports, dashboards, workflows.
Portal/Website Users would require custom pages for comparable analytics.

## Immutability model
Budgets approved at the start of the year become immutable to ensure consistent comparisons.
In-year changes are modeled as amendments with delta lines.

## Contract governance
Historical spend is imported into baseline.
Contracts/subscriptions are curated records that drive renewals and ongoing governance.

## Projects
Projects are standalone objects and can span multiple years.
To keep annual reporting consistent, per-year allocations are mandatory before approval.

