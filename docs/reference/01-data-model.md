# Reference: Data model (V1)

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
### MPIT Category (Tree DocType)
- category_name (Data)
- parent_category (Link: MPIT Category)
- is_group (Check)
- is_active (Check)
- sort_order (Int)

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
- category (Link: MPIT Category) [mandatory]
- cost_center (Link: MPIT Cost Center, optional)
- contract_kind (Select: Contract / Subscription / Annual Renewal / Maintenance)
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

## Budgeting
### MPIT Budget (Submittable)
- year (Link: MPIT Year) [mandatory]
- title (Data)
- lines (Table: MPIT Budget Line)
- total_amount_input (Currency, read-only sum of line amounts)
- total_amount_net (Currency, read-only sum of line net amounts)
- total_amount_vat (Currency, read-only sum of line VAT amounts)
- total_amount_gross (Currency, read-only sum of line gross amounts)
- workflow_state (managed by Workflow)

### MPIT Budget Line (Child Table)
- category (Link: MPIT Category) [mandatory]
- vendor (Link: MPIT Vendor, optional)
- contract (Link: MPIT Contract, optional)
- project (Link: MPIT Project, optional)
- cost_center (Link: MPIT Cost Center, optional; fetched from contract if set)
- description (Small Text)
- amount (Currency)
- amount_includes_vat (Check)
- vat_rate (Percent)
- amount_net (Currency, read-only)
- amount_vat (Currency, read-only)
- amount_gross (Currency, read-only)
- cost_type (Select: CAPEX / OPEX)
- recurrence (Select) + start_date/end_date (Date)
- is_active (Check)

## Actuals
### MPIT Actual Entry
- posting_date (Date) [mandatory]
- year (Link: MPIT Year) [derived from posting_date]
- category (Link: MPIT Category) [mandatory]
- vendor (Link: MPIT Vendor, optional)
- contract (Link: MPIT Contract, optional)
- project (Link: MPIT Project, optional)
- cost_center (Link: MPIT Cost Center, optional; mandatory for allowance spends in V2)
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
- planned_amount (Currency) [mandatory]

### MPIT Project Quote (Child Table)
- vendor (Link: MPIT Vendor)
- amount (Currency)
- quote_date (Date)
- attachment (Attach)
- status (Select: Received / Accepted / Rejected)

### MPIT Project Milestone (Child Table)
- title (Data)
- due_date (Date)
- status (Select: Planned / Done / Accepted)
- acceptance_date (Date)
- notes (Text)
- attachment (Attach)
