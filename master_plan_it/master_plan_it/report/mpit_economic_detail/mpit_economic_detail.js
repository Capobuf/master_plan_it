frappe.query_reports["MPIT Economic Detail"] = {
    filters: [
        { fieldname: "year", label: __("Year"), fieldtype: "Link", options: "MPIT Year", default: String(new Date().getFullYear()), reqd: 1 },
        { fieldname: "cost_center", label: __("Cost Center"), fieldtype: "Link", options: "MPIT Cost Center" },
        { fieldname: "include_children", label: __("Include Children"), fieldtype: "Check", default: 1, depends_on: "eval:doc.cost_center" },
        { fieldname: "project", label: __("Project"), fieldtype: "Link", options: "MPIT Project" },
        { fieldname: "contract", label: __("Contract"), fieldtype: "Link", options: "MPIT Contract" },
        { fieldname: "vendor", label: __("Vendor"), fieldtype: "Link", options: "MPIT Vendor" },
        { fieldname: "expense_kind", label: __("Expense Kind"), fieldtype: "Select", options: "All\nOrdinary\nPlafond", default: "All" },
        { fieldname: "row_phase", label: __("Row Phase"), fieldtype: "Select", options: "All\nEstimate\nQuote\nActual", default: "All" },
        { fieldname: "funding", label: __("Funding"), fieldtype: "Select", options: "All\nStandard\nPlafond\nExtra", default: "All" },
        { fieldname: "project_stage", label: __("Project Stage"), fieldtype: "Select", options: "All\nIdea\nProposed\nApproved\nDeferred\nRejected\nNo Project", default: "All" },
        { fieldname: "date_basis", label: __("Date Basis"), fieldtype: "Select", options: "Spend Date\nStart Date\nEnd Date\nEffective Period", default: "Spend Date" },
        { fieldname: "from_date", label: __("From Date"), fieldtype: "Date" },
        { fieldname: "to_date", label: __("To Date"), fieldtype: "Date" },
        { fieldname: "group_by", label: __("Group By"), fieldtype: "Select", options: "Cost Center", default: "Cost Center" },
        { fieldname: "show_replaced_cancelled", label: __("Show Replaced/Cancelled"), fieldtype: "Check", default: 0 },
        { fieldname: "print_profile", label: __("Print Profile"), fieldtype: "Select", options: "Executive\nStandard\nDetailed\nAudit", default: "Standard" },
        { fieldname: "print_orientation", label: __("Print Orientation"), fieldtype: "Select", options: "Auto\nPortrait\nLandscape", default: "Auto" },
        { fieldname: "print_density", label: __("Print Density"), fieldtype: "Select", options: "Normal\nCompact", default: "Normal" },
    ],

    formatter(value, row, column, data, default_formatter) {
        const formatted = default_formatter(value, row, column, data);
        if (column.fieldname === "status" && value === "Critical") {
            return `<span class="text-danger fw-semibold">${formatted}</span>`;
        }
        if (column.fieldname === "status" && value === "Warning") {
            return `<span class="text-warning fw-semibold">${formatted}</span>`;
        }
        if (column.fieldname === "recommended_action" && ["Expired", "Renegotiate"].includes(value)) {
            return `<span class="text-danger fw-semibold">${formatted}</span>`;
        }
        return formatted;
    },
};
