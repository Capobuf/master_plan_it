frappe.query_reports["MPIT Year End Forecast"] = {
    filters: [
        { fieldname: "year", label: __("Year"), fieldtype: "Link", options: "MPIT Year", default: String(new Date().getFullYear()), reqd: 1 },
        { fieldname: "cost_center", label: __("Cost Center"), fieldtype: "Link", options: "MPIT Cost Center" },
        { fieldname: "include_children", label: __("Include Children"), fieldtype: "Check", default: 1, depends_on: "eval:doc.cost_center" },
        { fieldname: "project", label: __("Project"), fieldtype: "Link", options: "MPIT Project" },
        { fieldname: "contract", label: __("Contract"), fieldtype: "Link", options: "MPIT Contract" },
        { fieldname: "vendor", label: __("Vendor"), fieldtype: "Link", options: "MPIT Vendor" },
        { fieldname: "basis", label: __("Basis"), fieldtype: "Select", options: "Actual only\nForecast only\nActual + Forecast\nActual + Approved Projects\nActual + Proposed\nFull Planning", default: "Actual + Forecast" },
        { fieldname: "period", label: __("Period"), fieldtype: "Select", options: "Monthly\nQuarterly\nHalf-year\nAnnual", default: "Monthly" },
        { fieldname: "cumulative", label: __("Cumulative"), fieldtype: "Check", default: 1 },
        { fieldname: "show_delta", label: __("Show Delta"), fieldtype: "Check", default: 1 },
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
