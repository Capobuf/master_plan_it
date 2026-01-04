// Copyright (c) 2026, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Overview"] = {
    filters: [
        {
            fieldname: "year",
            label: __("Year"),
            fieldtype: "Link",
            options: "MPIT Year",
            default: frappe.defaults.get_user_default("year"),
            reqd: 0
        },
        {
            fieldname: "cost_center",
            label: __("Cost Center"),
            fieldtype: "Link",
            options: "MPIT Cost Center",
            reqd: 0
        }
    ],

    onload: function (report) {
        // Create container for extra charts below report
        if (!document.getElementById("mpit-extra-charts")) {
            let container = document.createElement("div");
            container.id = "mpit-extra-charts";
            container.className = "row mt-4";
            // Insert after report-wrapper
            let wrapper = report.page.wrapper.find(".report-wrapper");
            if (wrapper.length) {
                wrapper.after(container);
            }
        }
    },

    after_datatable_render: function (datatable) {
        // Render extra charts from message payload
        let report = frappe.query_report;
        if (report && report.data && report.data.message && report.data.message.charts) {
            let container = document.getElementById("mpit-extra-charts");
            if (container) {
                container.innerHTML = ""; // Clear previous charts
                let charts = report.data.message.charts;
                for (let chart_name in charts) {
                    let chart_data = charts[chart_name];
                    let col = document.createElement("div");
                    col.className = "col-md-6 mb-4";
                    let card = document.createElement("div");
                    card.className = "frappe-card p-3";
                    let title = document.createElement("h6");
                    title.innerText = chart_data.title || chart_name;
                    card.appendChild(title);
                    let chartDiv = document.createElement("div");
                    chartDiv.id = "mpit-chart-" + chart_name;
                    card.appendChild(chartDiv);
                    col.appendChild(card);
                    container.appendChild(col);

                    // Render chart using frappe.Chart
                    new frappe.Chart(chartDiv, {
                        data: chart_data.data,
                        type: chart_data.type || "bar",
                        height: 250,
                        colors: chart_data.colors || undefined
                    });
                }
            }
        }
    }
};
