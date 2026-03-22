// Copyright (c) 2026, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Monthly Plan"] = {
    filters: [
        {
            fieldname: "year",
            label: __("Year"),
            fieldtype: "Link",
            options: "MPIT Year",
            reqd: 1,
            // No static default — resolved async in onload to avoid crash on fresh
            // installs where no fiscal_year user default is configured.
        },
        {
            fieldname: "cost_center",
            label: __("Cost Center"),
            fieldtype: "Link",
            options: "MPIT Cost Center",
        },
    ],

    onload: function (report) {
        // Mirror Python _resolve_year(): find the MPIT Year whose date range covers
        // today; if none exists, fall back to the most recent MPIT Year.
        //
        // Returning the Promise here is intentional: frappe.run_serially waits for
        // onload to settle before calling refresh(), so the year filter is populated
        // before the first mandatory-filter check fires.
        const today = frappe.datetime.get_today();

        return frappe.db
            .get_list("MPIT Year", {
                filters: [
                    ["start_date", "<=", today],
                    ["end_date", ">=", today],
                ],
                fields: ["name"],
                limit: 1,
            })
            .then(function (rows) {
                if (rows && rows.length) {
                    report.set_filter_value("year", rows[0].name);
                    return;
                }
                // No year covers today — use the most recent one.
                return frappe.db
                    .get_list("MPIT Year", {
                        fields: ["name"],
                        order_by: "year desc",
                        limit: 1,
                    })
                    .then(function (rows) {
                        if (rows && rows.length) {
                            report.set_filter_value("year", rows[0].name);
                        }
                    });
            });
    },
};
