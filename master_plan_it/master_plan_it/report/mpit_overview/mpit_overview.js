frappe.query_reports["MPIT Overview"] = {
    filters: [
        // ── Core context ──────────────────────────────────────────────
        {
            fieldname: "year",
            label: __("Year"),
            fieldtype: "Link",
            options: "MPIT Year",
            reqd: 1,
        },
        {
            fieldname: "view_mode",
            label: __("View Mode"),
            fieldtype: "Select",
            options: "Summary\nBuild-up\nLines",
            default: "Summary",
            reqd: 1,
            on_change: function () {
                // Re-render to show/hide context-sensitive filters
                frappe.query_report.refresh();
            },
        },
        {
            fieldname: "cost_center",
            label: __("Cost Center"),
            fieldtype: "Link",
            options: "MPIT Cost Center",
        },

        // ── Scope filters ─────────────────────────────────────────────
        {
            fieldname: "section_scope",
            label: __("Section Scope"),
            fieldtype: "Select",
            options: "All\nContracts\nExpenses\nPlafond",
            default: "All",
            depends_on: "eval:['Build-up','Lines'].includes(doc.view_mode)",
        },
        {
            fieldname: "expense_phase",
            label: __("Expense Phase"),
            fieldtype: "Select",
            options: "All\nEstimate\nQuote\nActual",
            default: "All",
            // Only meaningful in Lines mode when expenses are in scope
            depends_on: "eval:doc.view_mode === 'Lines' && ['All','Expenses'].includes(doc.section_scope || 'All')",
        },

        // ── Entity filters ────────────────────────────────────────────
        {
            fieldname: "contract",
            label: __("Contract"),
            fieldtype: "Link",
            options: "MPIT Contract",
            depends_on: "eval:['Build-up','Lines'].includes(doc.view_mode)",
        },
        {
            // project applies to expenses only; hidden in Summary to avoid
            // misrepresenting contract figures that have no project link.
            fieldname: "project",
            label: __("Project"),
            fieldtype: "Link",
            options: "MPIT Project",
            depends_on: "eval:['Build-up','Lines'].includes(doc.view_mode) && ['All','Expenses'].includes(doc.section_scope || 'All')",
            description: __("Filters expense lines only. Contract lines are not linked to projects."),
        },
        {
            fieldname: "vendor",
            label: __("Vendor"),
            fieldtype: "Link",
            options: "MPIT Vendor",
            depends_on: "eval:['Build-up','Lines'].includes(doc.view_mode)",
        },
        {
            fieldname: "show_zero_rows",
            label: __("Show Zero Rows"),
            fieldtype: "Check",
            default: 0,
        },

        // ── Print filters ─────────────────────────────────────────────
        {
            fieldname: "print_profile",
            label: __("Print Profile"),
            fieldtype: "Select",
            options: "Standard\nCompact\nAll",
            default: "Standard",
        },
        {
            fieldname: "print_orientation",
            label: __("Print Orientation"),
            fieldtype: "Select",
            options: "Auto\nPortrait\nLandscape",
            default: "Auto",
        },
        {
            fieldname: "print_density",
            label: __("Print Density"),
            fieldtype: "Select",
            options: "Normal\nCompact\nUltra",
            default: "Normal",
        },
    ],
};
