frappe.query_reports["MPIT Budget Variance"] = {
    filters: [
        { fieldname: "year", label: __("Year"), fieldtype: "Link", options: "MPIT Year", default: String(new Date().getFullYear()), reqd: 1 },
        { fieldname: "cost_center", label: __("Cost Center"), fieldtype: "Link", options: "MPIT Cost Center" },
        { fieldname: "include_children", label: __("Include Children"), fieldtype: "Check", default: 1, depends_on: "eval:doc.cost_center" },
        { fieldname: "group_by", label: __("Group By"), fieldtype: "Select", options: "Cost Center\nVendor\nProject\nProject Stage\nFunding", default: "Cost Center" },
        { fieldname: "period", label: __("Period"), fieldtype: "Select", options: "Annual\nQuarterly\nMonthly", default: "Annual" },
        { fieldname: "variance_basis", label: __("Variance Basis"), fieldtype: "Select", options: "Actual\nYear-end Forecast\nCommitted\nFull Planning", default: "Year-end Forecast" },
        { fieldname: "only_variances", label: __("Only Variances"), fieldtype: "Check", default: 1 },
        { fieldname: "min_variance_amount", label: __("Min Variance Amount"), fieldtype: "Currency", default: 0 },
        { fieldname: "min_variance_percent", label: __("Min Variance %"), fieldtype: "Percent", default: 5 },
        { fieldname: "show_positive_variance", label: __("Show Positive Variance"), fieldtype: "Check", default: 1 },
        { fieldname: "show_negative_variance", label: __("Show Negative Variance"), fieldtype: "Check", default: 1 },
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
