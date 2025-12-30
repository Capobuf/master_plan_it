# Field Help Text Report

## Inventory summary
- Before: 16 DocTypes with 171 meaningful fields missing description.
- After: all meaningful fields now have descriptions with Italian translations in `apps/master_plan_it/master_plan_it/translations/it.csv`.
- Context format for translations: `DocType:<Doctype> Field:<fieldname>`.
- Stop questions: none (all meanings derived from schema and controllers).

## Per DocType updates
### MPIT Actual Entry

- posting_date: Posting date for the entry; used to derive the MPIT Year.
- year: Derived MPIT Year based on the posting date.
- category: Select the category for this entry.
- vendor: Select the vendor involved in this entry.
- contract: Select the related contract, if applicable.
- project: Select the related project, if applicable.
- budget: Select the budget this entry relates to.
- budget_line_ref: Reference to the related budget line, if applicable.
- amount: Enter the actual amount; use net or gross per the VAT setting.
- amount_includes_vat: If enabled, the amount includes VAT.
- vat_rate: VAT rate applied to the amount.
- amount_net: Calculated automatically from amount and VAT settings.
- amount_vat: Calculated automatically as the VAT portion.
- amount_gross: Calculated automatically as the gross amount.
- description: Describe the actual entry.
- status: Choose whether the entry is recorded or verified.

### MPIT Budget

- year: Select the budget year.
- title: Enter a short budget title.
- workflow_state: Workflow status set by the approval process.
- lines: Add budget lines for categories, vendors, and amounts.
- total_amount_input: Calculated automatically as the sum of line amounts.
- total_amount_vat: Calculated automatically as the sum of line VAT amounts.
- total_amount_net: Calculated automatically as the sum of net amounts.
- total_amount_gross: Calculated automatically as the sum of gross amounts.
- amended_from: Reference to the original budget this document was amended from.

### MPIT Budget Amendment

- budget: Select the budget being amended.
- effective_date: Date when this amendment takes effect.
- reason: Describe why the budget is being amended.
- workflow_state: Workflow status set by the approval process.
- lines: Add amendment lines detailing the changes.
- amended_from: Reference to the original amendment this document was copied from.

### MPIT Budget Line

- category: Select the budget category.
- vendor: Select the vendor for this line.
- description: Describe this budget line.
- recurrence_rule: Choose how this amount recurs for annualization.
- period_start_date: Start date of the period used to calculate overlap with the budget year.
- period_end_date: End date of the period used to calculate overlap with the budget year.
- amount: Enter the budgeted amount; use net or gross per the VAT setting.
- amount_includes_vat: If enabled, the amount includes VAT.
- amount_gross: Calculated automatically as the gross amount.
- vat_rate: VAT rate applied to the amount.
- amount_net: Calculated automatically from amount and VAT settings.
- amount_vat: Calculated automatically as the VAT portion.
- contract: Select the related contract, if applicable.
- project: Select the related project, if applicable.
- annual_net: Calculated automatically as the annualized net amount.
- annual_vat: Calculated automatically as the annualized VAT amount.
- annual_gross: Calculated automatically as the annualized gross amount.
- cost_type: Choose whether the cost is CAPEX or OPEX.
- is_active: If enabled, the budget line is active.

### MPIT Category

- category_name: Enter the category name.
- parent_category: Select the parent category.
- is_active: If enabled, the category is active for use.
- sort_order: Enter numeric order for sorting categories.
- lft: Calculated automatically for tree positioning.
- rgt: Calculated automatically for tree positioning.
- is_group: If enabled, category can contain child categories.
- old_parent: Previous parent category captured during moves.
- parent_mpit_category: Parent category used by tree view.

### MPIT Contract

- title: Enter the contract title.
- vendor: Select the vendor for this contract.
- category: Select the category for this contract.
- contract_kind: Choose the contract type (contract, subscription, renewal, or maintenance).
- billing_cycle: Choose the billing cycle for this contract.
- start_date: Date when the contract starts.
- end_date: Date when the contract ends.
- next_renewal_date: Next renewal date for this contract.
- notice_days: Number of notice days required before renewal.
- auto_renew: If enabled, the contract renews automatically.
- current_amount: Enter the current contract amount; use net or gross per the VAT setting.
- current_amount_includes_vat: If enabled, the current amount includes VAT.
- vat_rate: VAT rate applied to the current amount.
- current_amount_net: Calculated automatically from current amount and VAT settings.
- current_amount_vat: Calculated automatically as the VAT portion.
- current_amount_gross: Calculated automatically as the gross amount.
- spread_months: Number of months to spread a prepaid amount.
- spread_start_date: Start date of the spread period.
- spread_end_date: Computed end date of the spread period.
- rate_schedule: Table of rate changes; mutually exclusive with spread.
- status: Choose the current contract status.
- owner_user: Select the contract owner.
- notes: Add internal notes about the contract.
- attachment: Upload the contract file.

### MPIT Project

- title: Enter the project title.
- description: Add a brief description of the project.
- status: Choose the project status; at least one allocation is required before approval or later states.
- start_date: Date when the project starts.
- end_date: Date when the project ends.
- owner_user: Select the project owner.
- allocations: Add one or more allocations with year and planned amount.
- quotes: Add vendor quotes for this project.
- milestones: Add project milestones.

### MPIT Project Allocation

- year: Select the year for this allocation.
- planned_amount: Enter the planned amount for this year.
- planned_amount_includes_vat: If enabled, the planned amount includes VAT.
- planned_amount_gross: Calculated automatically as the gross amount.
- vat_rate: VAT rate applied to the planned amount.
- planned_amount_net: Calculated automatically from planned amount and VAT settings.
- planned_amount_vat: Calculated automatically as the VAT portion.

### MPIT Project Milestone

- title: Enter the milestone title.
- status: Choose the milestone status.
- due_date: Date when the milestone is due.
- acceptance_date: Date when the milestone was accepted.
- attachment: Upload any supporting attachment.
- notes: Add notes about the milestone.

### MPIT Project Quote

- vendor: Select the vendor providing the quote.
- quote_date: Date when the quote was received.
- attachment: Upload the quote document.
- status: Choose the quote status.
- amount: Enter the quoted amount.
- amount_gross: Calculated automatically as the gross amount.
- amount_includes_vat: If enabled, the amount includes VAT.
- vat_rate: VAT rate applied to the quote amount.
- amount_net: Calculated automatically from amount and VAT settings.
- amount_vat: Calculated automatically as the VAT portion.

### MPIT Settings

- currency: Select the default currency for Master Plan IT calculations.
- renewal_window_days: Days before renewal dates treated as the renewal window.

### MPIT User Preferences

- user: Select the user these preferences apply to.
- default_vat_rate: Default VAT rate for new entries (leave blank for no default, 0 is valid)
- default_amount_includes_vat: If enabled, new amounts default to VAT-inclusive.
- budget_prefix: Prefix used when generating budget names.
- budget_sequence_digits: Number of digits for budget sequence numbers.
- project_prefix: Prefix used when generating project names.
- project_sequence_digits: Number of digits for project sequence numbers.
- show_attachments_in_print: If enabled, attachments are shown on print formats.

### MPIT Vendor

- vendor_name: Enter the vendor name.
- vat_id: Enter the vendor VAT ID.
- contact_email: Enter the main contact email.
- contact_phone: Enter the main contact phone number.
- notes: Add internal notes about the vendor.
- is_active: If enabled, the vendor is active for selection.

### MPIT Year

- year: Enter the year identifier (e.g. 2025).
- start_date: Date when the year period starts.
- end_date: Date when the year period ends.
- is_active: If enabled, the year is active for planning.
