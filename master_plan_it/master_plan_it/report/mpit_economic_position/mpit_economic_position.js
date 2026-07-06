frappe.query_reports["MPIT Economic Position"] = {
    filters: [
        { fieldname: "year", label: __("Year"), fieldtype: "Link", options: "MPIT Year", default: String(new Date().getFullYear()), reqd: 1 },
        { fieldname: "cost_center", label: __("Cost Center"), fieldtype: "Link", options: "MPIT Cost Center" },
        { fieldname: "include_children", label: __("Include Children"), fieldtype: "Check", default: 1, depends_on: "eval:doc.cost_center" },
        { fieldname: "group_by", label: __("Group By"), fieldtype: "Select", options: "Cost Center\nVendor\nProject\nProject Stage\nFunding", default: "Cost Center" },
        { fieldname: "basis", label: __("Basis"), fieldtype: "Select", options: "Actual only\nForecast only\nActual + Forecast\nActual + Approved Projects\nActual + Proposed\nFull Planning", default: "Actual + Forecast" },
        { fieldname: "scope", label: __("Scope"), fieldtype: "Select", options: "All\nExpenses\nPlafond\nExtra", default: "All" },
        { fieldname: "show_only_exceptions", label: __("Show Only Exceptions"), fieldtype: "Check", default: 0 },
        { fieldname: "hide_zero_rows", label: __("Hide Zero Rows"), fieldtype: "Check", default: 1 },
        { fieldname: "warning_threshold_percent", label: __("Warning Threshold %"), fieldtype: "Percent", default: 85 },
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
