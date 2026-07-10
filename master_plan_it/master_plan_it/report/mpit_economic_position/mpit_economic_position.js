frappe.query_reports["MPIT Economic Position"] = {
    filters: [
        { fieldname: "year", label: __("Year"), fieldtype: "Link", options: "MPIT Year", default: String(new Date().getFullYear()), reqd: 1 },
        { fieldname: "cost_center", label: __("Cost Center"), fieldtype: "Link", options: "MPIT Cost Center" },
        { fieldname: "include_children", label: __("Include Children"), fieldtype: "Check", default: 1, depends_on: "eval:doc.cost_center" },
        {
            fieldname: "group_by",
            label: __("Group By"),
            fieldtype: "Select",
            options: "Cost Center\nVendor\nProject\nProject Stage\nFunding",
            default: "Cost Center",
            description: __("Plafond availability is shown only when grouping by Cost Center or Funding."),
        },
        {
            fieldname: "basis",
            label: __("Forecast Scope"),
            fieldtype: "Select",
            options: "Actual + Approved Projects\nActual + Proposed\nFull Planning",
            default: "Actual + Proposed",
            description: __("Choose which active estimates and quotes are included in the remaining forecast."),
        },
        {
            fieldname: "scope",
            label: __("Funding Scope"),
            fieldtype: "Select",
            options: "All\nStandard\nPlafond\nExtra",
            default: "All",
            description: __("Limit the report to standard, plafond-funded, or extra expenses."),
        },
        { fieldname: "hide_zero_rows", label: __("Hide Zero Rows"), fieldtype: "Check", default: 1 },
    ],

    formatter(value, row, column, data, default_formatter) {
        const formatted = default_formatter(value, row, column, data);
        if (column.fieldname === "usage_percent") {
            return `<span class="fw-semibold">${formatted}</span>`;
        }
        return formatted;
    },
};
