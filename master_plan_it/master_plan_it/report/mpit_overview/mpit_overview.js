const OVERVIEW_METRIC_FIELDS = new Set([
    "forecast_contracts",
    "forecast_estimate",
    "forecast_quote",
    "forecast_total",
    "actual_standard",
    "actual_on_plafond",
    "actual_extra",
    "actual_total",
    "plafond",
    "plafond_consumed",
    "remaining",
    "over",
]);

const OVERVIEW_TOTAL_FIELDS = new Set(["forecast_total", "actual_total"]);
const OVERVIEW_MAIN_LABEL_FIELDS = new Set(["cost_center"]);

function toNumericValue(value) {
    if (value === null || value === undefined || value === "") {
        return null;
    }
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : null;
}

function withCellClass(content, classes) {
    return `<span class="${classes}">${content}</span>`;
}

frappe.query_reports["MPIT Overview"] = {
    filters: [
        // ── Core context ──────────────────────────────────────────────
        {
            fieldname: "year",
            label: __("Year"),
            fieldtype: "Link",
            options: "MPIT Year",
            default: String(new Date().getFullYear()),
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
            // misrepresenting contract figures in this report path.
            fieldname: "project",
            label: __("Project"),
            fieldtype: "Link",
            options: "MPIT Project",
            depends_on: "eval:['Build-up','Lines'].includes(doc.view_mode) && ['All','Expenses'].includes(doc.section_scope || 'All')",
            description: __("Filters expense lines only. Contract lines are intentionally not project-filtered."),
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

    get_datatable_options(options) {
        return Object.assign({}, options, {
            serialNoColumn: false,
            layout: "fixed",
        });
    },

    formatter(value, row, column, data, default_formatter) {
        const formatted = default_formatter(value, row, column, data);
        const fieldname = column?.fieldname;

        if (!fieldname) {
            return formatted;
        }

        if (OVERVIEW_MAIN_LABEL_FIELDS.has(fieldname)) {
            return withCellClass(formatted, "fw-semibold");
        }

        if (!OVERVIEW_METRIC_FIELDS.has(fieldname)) {
            return formatted;
        }

        const numericValue = toNumericValue(value);
        if (numericValue === 0) {
            return withCellClass(formatted, "text-muted");
        }

        if (fieldname === "remaining" && numericValue > 0) {
            return withCellClass(formatted, "text-success fw-semibold");
        }

        if (fieldname === "over" && numericValue > 0) {
            return withCellClass(formatted, "text-danger fw-semibold");
        }

        if (OVERVIEW_TOTAL_FIELDS.has(fieldname) || data?.bold) {
            return withCellClass(formatted, "fw-semibold");
        }

        return formatted;
    },
};
