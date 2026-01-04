// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Cost Centers"] = {
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
            fieldname: "parent_mpit_cost_center",
            label: __("Parent Cost Center"),
            fieldtype: "Link",
            options: "MPIT Cost Center",
            reqd: 0
        }
    ],

    onload: function (report) {
        // Create container for extra charts below report
        if (!document.getElementById("mpit-cc-extra-charts")) {
            let container = document.createElement("div");
            container.id = "mpit-cc-extra-charts";
            container.className = "row mt-4";
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
            let container = document.getElementById("mpit-cc-extra-charts");
            if (container) {
                container.innerHTML = "";
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
                    chartDiv.id = "mpit-cc-chart-" + chart_name;
                    card.appendChild(chartDiv);
                    col.appendChild(card);
                    container.appendChild(col);

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
