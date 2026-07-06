frappe.query_reports["MPIT Year Comparison"] = {
    filters: [
        { fieldname: "year_a", label: __("Year A"), fieldtype: "Link", options: "MPIT Year", default: String(new Date().getFullYear() - 1), reqd: 1 },
        { fieldname: "year_b", label: __("Year B"), fieldtype: "Link", options: "MPIT Year", default: String(new Date().getFullYear()), reqd: 1 },
        { fieldname: "group_by", label: __("Group By"), fieldtype: "Select", options: "Cost Center\nVendor\nProject\nContract\nFunding\nProject Stage", default: "Cost Center" },
        { fieldname: "basis", label: __("Basis"), fieldtype: "Select", options: "Year-end Forecast", default: "Year-end Forecast" },
        { fieldname: "only_changed", label: __("Only Changed"), fieldtype: "Check", default: 1 },
        { fieldname: "min_delta_amount", label: __("Min Delta Amount"), fieldtype: "Currency", default: 0 },
        { fieldname: "min_delta_percent", label: __("Min Delta %"), fieldtype: "Percent", default: 5 },
        { fieldname: "show_new_items", label: __("Show New Items"), fieldtype: "Check", default: 1 },
        { fieldname: "show_removed_items", label: __("Show Removed Items"), fieldtype: "Check", default: 1 },
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
