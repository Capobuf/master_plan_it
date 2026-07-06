frappe.query_reports["MPIT What If Scenario"] = {
    filters: [
        { fieldname: "year", label: __("Year"), fieldtype: "Link", options: "MPIT Year", default: String(new Date().getFullYear()), reqd: 1 },
        { fieldname: "cost_center", label: __("Cost Center"), fieldtype: "Link", options: "MPIT Cost Center" },
        { fieldname: "include_children", label: __("Include Children"), fieldtype: "Check", default: 1, depends_on: "eval:doc.cost_center" },
        { fieldname: "scenario", label: __("Scenario"), fieldtype: "Select", options: "Base\nConservative\nProposals\nMaximum\nApproved Only\nActual Only", default: "Base" },
        { fieldname: "include_approved_projects", label: __("Include Approved Projects"), fieldtype: "Check", default: 1 },
        { fieldname: "include_proposed_projects", label: __("Include Proposed Projects"), fieldtype: "Check", default: 0 },
        { fieldname: "include_ideas", label: __("Include Ideas"), fieldtype: "Check", default: 0 },
        { fieldname: "include_extra", label: __("Include Extra"), fieldtype: "Check", default: 1 },
        { fieldname: "include_plafond", label: __("Include Plafond"), fieldtype: "Check", default: 1 },
        { fieldname: "include_renewals", label: __("Include Renewals"), fieldtype: "Check", default: 1 },
        { fieldname: "renewal_increase_percent", label: __("Renewal Increase %"), fieldtype: "Percent", default: 0 },
        { fieldname: "contingency_percent", label: __("Contingency %"), fieldtype: "Percent", default: 0 },
        { fieldname: "show_detail", label: __("Show Detail"), fieldtype: "Check", default: 0 },
        { fieldname: "group_by", label: __("Group By"), fieldtype: "Select", options: "Scenario Component", default: "Scenario Component" },
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
