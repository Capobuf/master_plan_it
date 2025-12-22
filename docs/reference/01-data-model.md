# Reference: Data model (V1)

Naming convention: all DocTypes are prefixed `MPIT`.

## Setup
### MPIT Settings (Single)
- currency (Link: Currency)
- renewal_window_days (Int, default 90)
- portfolio_warning_threshold_pct (Int, default 100)

### MPIT User Preferences
- user (Link: User) — primary key/autoname (autoname = field:user)
- default_vat_rate (Percent, nullable) — **no default**; if missing, strict VAT validation blocks saves when required
- default_amount_includes_vat (Check, default 0)
- show_attachments_in_print (Check, default 0)
- budget_prefix (Data, optional)
- budget_sequence_digits (Int, default 2)
- project_prefix (Data, optional)
- project_sequence_digits (Int, default 4)

> Permissions: owner-only (via if_owner) with `System Manager` allowed global read/write.

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

### MPIT Vendor
- vendor_name (Data, unique)
- vat_id (Data, optional)
- contact_email (Data, optional)
- contact_phone (Data, optional)
- notes (Text)
- is_active (Check)

## Baseline
### MPIT Baseline Expense
- year (Link: MPIT Year) [recommended mandatory]
- posting_date (Date)
- category (Link: MPIT Category) [mandatory]
- vendor (Link: MPIT Vendor) [optional]
- description (Small Text)
- amount (Currency)
- expense_kind (Select: One-off / Subscription / Annual Renewal / Contract)
- recurrence (Select: Monthly / Quarterly / Annual / Custom / None)
- contract_start (Date, optional)
- contract_end (Date, optional)
- auto_renew (Check)
- notice_days (Int)
- status (Select: In Review / Needs Clarification / Validated / Archived)

## Contracts
### MPIT Contract
- title (Data)
- vendor (Link: MPIT Vendor) [mandatory]
- category (Link: MPIT Category) [mandatory]
- contract_kind (Select: Contract / Subscription / Annual Renewal / Maintenance)
- billing_cycle (Select: Monthly / Quarterly / Annual / Other)
- start_date (Date, optional)
- end_date (Date, optional)
- next_renewal_date (Date) [mandatory]
- notice_days (Int, optional)
- auto_renew (Check)
- current_amount (Currency, optional)
- currency (Link: Currency, optional)
- status (Select: Draft / Active / Pending Renewal / Renewed / Cancelled / Expired)
- owner_user (Link: User, optional)
- source_baseline_expense (Link: MPIT Baseline Expense, optional)
- attachments (native Attach)

## Budgeting
### MPIT Budget (Submittable)
- year (Link: MPIT Year) [mandatory]
- title (Data)
- currency (Link: Currency)
- lines (Table: MPIT Budget Line)
- workflow_state (managed by Workflow)

### MPIT Budget Line (Child Table)
- category (Link: MPIT Category) [mandatory]
- vendor (Link: MPIT Vendor, optional)
- contract (Link: MPIT Contract, optional)
- project (Link: MPIT Project, optional)
- baseline_expense (Link: MPIT Baseline Expense, optional)
- description (Small Text)
- amount (Currency)
- cost_type (Select: CAPEX / OPEX)
- recurrence (Select) + start_date/end_date (Date)
- is_portfolio_bucket (Check)
- is_active (Check)

### MPIT Budget Amendment (Submittable)
- budget (Link: MPIT Budget) [mandatory]
- effective_date (Date)
- reason (Text)
- lines (Table: MPIT Amendment Line)
- workflow_state

### MPIT Amendment Line (Child Table)
- category (Link: MPIT Category) [mandatory]
- vendor (Link: MPIT Vendor, optional)
- contract (Link: MPIT Contract, optional)
- project (Link: MPIT Project, optional)
- description (Small Text)
- delta_amount (Currency) [mandatory]
- note (Text)

## Actuals
### MPIT Actual Entry
- posting_date (Date) [mandatory]
- year (Link: MPIT Year) [derived from posting_date]
- category (Link: MPIT Category) [mandatory]
- vendor (Link: MPIT Vendor, optional)
- contract (Link: MPIT Contract, optional)
- project (Link: MPIT Project, optional)
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
- currency (Link: Currency)
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

