frappe.query_reports["MPIT Renewals And Commitments"] = {
    filters: [
        { fieldname: "from_date", label: __("From Date"), fieldtype: "Date", default: frappe.datetime.get_today() },
        { fieldname: "days", label: __("Days"), fieldtype: "Int", default: 90 },
        { fieldname: "cost_center", label: __("Cost Center"), fieldtype: "Link", options: "MPIT Cost Center" },
        { fieldname: "include_children", label: __("Include Children"), fieldtype: "Check", default: 1, depends_on: "eval:doc.cost_center" },
        { fieldname: "auto_renew_only", label: __("Auto Renew Only"), fieldtype: "Check", default: 0 },
        { fieldname: "include_past", label: __("Include Past"), fieldtype: "Check", default: 0 },
        { fieldname: "min_annual_amount", label: __("Min Annual Amount"), fieldtype: "Currency", default: 0 },
        { fieldname: "action_status", label: __("Action Status"), fieldtype: "Select", options: "All\nRenew\nReview\nRenegotiate\nExpired", default: "All" },
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
