# Reference: Data model (V2, Cost Center only)

Naming convention: all DocTypes are prefixed `MPIT`.

## Setup
### MPIT Settings (Single)
- currency (Link: Currency)
- renewal_window_days (Int, default 90)

All monetary fields use the single currency configured in MPIT Settings; documents do not store their own currency choice.

### MPIT Year
- year (Int, unique)
- start_date (Date)
- end_date (Date)
- is_active (Check)

## Classification
### MPIT Cost Center (Tree DocType)
- cost_center_name (Data)
- parent_cost_center (Link: MPIT Cost Center)
- is_group (Check)
- is_active (Check)

### MPIT Vendor
- vendor_name (Data, unique)
- vat_id (Data, optional)
- contact_email (Data, optional)
- contact_phone (Data, optional)
- notes (Text)
- is_active (Check)

## Contracts
### MPIT Contract
- title (Data)
- vendor (Link: MPIT Vendor) [mandatory]
- cost_center (Link: MPIT Cost Center) [mandatory]
- contract_kind (Select: Contract / Subscription / Annual Renewal / Maintenance)
- spread_months (Int), spread_start_date (Date), spread_end_date (Date, computed)
- rate_schedule (Table: MPIT Contract Rate, mutually exclusive with spread)
- billing_cycle (Select: Monthly / Quarterly / Annual / Other)
- start_date (Date, optional)
- end_date (Date, optional)
- next_renewal_date (Date) [mandatory]
- notice_days (Int, optional)
- auto_renew (Check)
- current_amount (Currency, optional)
- status (Select: Draft / Active / Pending Renewal / Renewed / Cancelled / Expired)
- owner_user (Link: User, optional)
- attachments (native Attach)

### MPIT Contract Rate (Child Table)
- effective_from (Date) [mandatory]
- amount (Currency) [mandatory]
- amount_includes_vat (Check)
- vat_rate (Percent)
- amount_net/vat/gross (Currency, read-only)

## Budgeting
### MPIT Budget (Submittable)
- year (Link: MPIT Year) [mandatory]
- title (Data)
- budget_kind (Select: Baseline / Forecast)
- is_active_forecast (Check, only for Forecast; unique per year)
- baseline_ref (Link: MPIT Budget, reference Baseline for a Forecast)
- lines (Table: MPIT Budget Line)
- total_amount_input (Currency, read-only sum of line amounts)
- total_amount_net (Currency, read-only sum of line net amounts)
- total_amount_vat (Currency, read-only sum of line VAT amounts)
- total_amount_gross (Currency, read-only sum of line gross amounts)
- workflow_state (managed by Workflow)

### MPIT Budget Line (Child Table)
- vendor (Link: MPIT Vendor, optional)
- contract (Link: MPIT Contract, optional)
- project (Link: MPIT Project, optional)
- cost_center (Link: MPIT Cost Center, mandatory; fetched from contract/project if set)
- description (Small Text)
- line_kind (Select: Contract / Project / Allowance / Manual)
- source_key (Data, read-only)
- amount (Currency)
- amount_includes_vat (Check)
- vat_rate (Percent)
- amount_net (Currency, read-only)
- amount_vat (Currency, read-only)
- amount_gross (Currency, read-only)
- cost_type (Select: CAPEX / OPEX)
- recurrence (Select) + start_date/end_date (Date)
- is_active (Check)
- is_generated (Check, read-only in practice for generated lines)

## Actuals
### MPIT Actual Entry
- posting_date (Date) [mandatory]
- year (Link: MPIT Year) [derived from posting_date]
- vendor (Link: MPIT Vendor, optional)
- contract (Link: MPIT Contract, optional)
- project (Link: MPIT Project, optional)
- cost_center (Link: MPIT Cost Center) [mandatory for allowance; defaulted from contract/project if set]
- amount (Currency) [mandatory]
- budget (Link: MPIT Budget, optional)
- budget_line_ref (Data/Small Text, optional)
- description (Small Text)
- status (Select: Recorded / Verified)

## Projects
### MPIT Project
- title (Data)
- description (Text)
- status (Select: Draft / Proposed / Approved / In Progress / On Hold / Completed / Cancelled)
- start_date (Date)
- end_date (Date)
- owner (Link: User)
- cost_center (Link: MPIT Cost Center)
- allocations (Table: MPIT Project Allocation) [mandatory before approval]
- quotes (Table: MPIT Project Quote) [optional]
- milestones (Table: MPIT Project Milestone) [optional]

### MPIT Project Allocation (Child Table)
- year (Link: MPIT Year) [mandatory]
- cost_center (Link: MPIT Cost Center) [mandatory]
- planned_amount (Currency) [mandatory]
- VAT split fields

### MPIT Project Quote (Child Table)
- cost_center (Link: MPIT Cost Center) [mandatory]
- vendor (Link: MPIT Vendor)
- amount (Currency)
- quote_date (Date)
- attachment (Attach)
- status (Select: Informational / Approved; default Informational)
- VAT split fields

### MPIT Project Milestone (Child Table)
- title (Data)
- due_date (Date)
- status (Select: Planned / Done / Accepted)
- acceptance_date (Date)
- notes (Text)
- attachment (Attach)
