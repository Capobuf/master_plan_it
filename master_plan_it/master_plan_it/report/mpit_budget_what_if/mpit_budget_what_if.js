// Copyright (c) 2026, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Budget What-If"] = {
    filters: [
        {
            fieldname: "budget",
            label: __("Budget"),
            fieldtype: "Link",
            options: "MPIT Budget",
            reqd: 1,
            get_query: function () {
                return {
                    filters: {
                        docstatus: ["in", [0, 1]]
                    }
                };
            },
            on_change: function () {
                // Clear projects when budget changes
                frappe.query_report.set_filter_value("projects", "");
            }
        },
        {
            fieldname: "projects",
            label: __("Projects"),
            fieldtype: "Data",
            description: __("Click to select projects"),
            read_only: 1,
            on_change: function () {
                frappe.query_report.refresh();
            }
        }
    ],

    onload: function (report) {
        // Add custom button to select projects
        report.page.add_inner_button(__("Select Projects"), function () {
            show_project_selector(report);
        });

        // Make the projects field clickable
        setTimeout(function () {
            let projects_field = frappe.query_report.page.fields_dict.projects;
            if (projects_field && projects_field.$input) {
                projects_field.$input.css("cursor", "pointer");
                projects_field.$input.on("click", function () {
                    show_project_selector(report);
                });
            }
        }, 500);

        // Create container for extra charts
        if (!document.getElementById("mpit-whatif-charts")) {
            let container = document.createElement("div");
            container.id = "mpit-whatif-charts";
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
        if (report && report.data && report.data.message) {
            render_monthly_chart(report.data.message);
        }

        // Style rows based on type
        style_report_rows();
    },

    formatter: function (value, row, column, data, default_formatter) {
        value = default_formatter(value, row, column, data);

        if (data && data.row_type === "header") {
            value = `<strong>${value}</strong>`;
        }

        if (data && data.row_type === "cost_center" && column.fieldname === "delta") {
            // Show simulated value with delta indicator
            let delta = data.cc_delta || 0;
            let sign = delta > 0 ? "+" : "";
            value = `${value} <small style="color: var(--text-muted)">(${sign}${format_number(delta)})</small>`;
        }

        if (column.fieldname === "action_label" && data && data.row_type === "project") {
            if (data.action_label && data.action_label.includes("+")) {
                value = `<span class="text-success">${value}</span>`;
            } else if (data.action_label && data.action_label.includes("-")) {
                value = `<span class="text-danger">${value}</span>`;
            }
        }

        if (column.fieldname === "delta" && data && data.row_type === "project") {
            let delta = data.delta || 0;
            if (delta > 0) {
                value = `<span class="text-success">${value}</span>`;
            } else if (delta < 0) {
                value = `<span class="text-danger">${value}</span>`;
            }
        }

        return value;
    }
};

function show_project_selector(report) {
    // Get currently selected projects
    let current_projects_str = frappe.query_report.get_filter_value("projects") || "";
    let selected_projects = current_projects_str ? current_projects_str.split(",").map(p => p.trim()) : [];

    // Fetch all projects
    frappe.call({
        method: "frappe.client.get_list",
        args: {
            doctype: "MPIT Project",
            fields: ["name", "title", "workflow_state", "cost_center", "planned_total_net"],
            order_by: "workflow_state asc, title asc",
            limit_page_length: 0
        },
        async: false,
        callback: function (r) {
            if (r.message) {
                let projects = r.message;

                // Group by status
                let approved = projects.filter(p => p.workflow_state === "Approved");
                let non_approved = projects.filter(p => p.workflow_state !== "Approved");

                // Build dialog fields
                let fields = [
                    {
                        fieldtype: "HTML",
                        fieldname: "info",
                        options: `
                            <div class="alert alert-info">
                                <strong>${__("Logic")}:</strong><br>
                                <span class="text-success">+ ${__("Non-Approved projects")}</span>: ${__("will be ADDED to budget")}<br>
                                <span class="text-danger">- ${__("Approved projects")}</span>: ${__("will be REMOVED from budget")}
                            </div>
                        `
                    },
                    {
                        fieldtype: "Section Break",
                        label: __("Non-Approved Projects (+ Add)")
                    }
                ];

                // Add non-approved projects
                for (let p of non_approved) {
                    fields.push({
                        fieldtype: "Check",
                        fieldname: `proj_${p.name}`,
                        label: `${p.title} (${p.workflow_state}) - ${p.cost_center || "No CC"} - ${format_number(p.planned_total_net || 0)}`,
                        default: selected_projects.includes(p.name) ? 1 : 0
                    });
                }

                fields.push({
                    fieldtype: "Section Break",
                    label: __("Approved Projects (- Remove)")
                });

                // Add approved projects
                for (let p of approved) {
                    fields.push({
                        fieldtype: "Check",
                        fieldname: `proj_${p.name}`,
                        label: `${p.title} - ${p.cost_center || "No CC"} - ${format_number(p.planned_total_net || 0)}`,
                        default: selected_projects.includes(p.name) ? 1 : 0
                    });
                }

                // Show dialog
                let d = new frappe.ui.Dialog({
                    title: __("Select Projects for What-If Analysis"),
                    fields: fields,
                    size: "large",
                    primary_action_label: __("Apply"),
                    primary_action: function (values) {
                        // Extract selected projects
                        let selected = [];
                        for (let key in values) {
                            if (key.startsWith("proj_") && values[key]) {
                                selected.push(key.replace("proj_", ""));
                            }
                        }

                        // Update filter
                        frappe.query_report.set_filter_value("projects", selected.join(","));
                        d.hide();
                    }
                });

                d.show();
            }
        }
    });
}

function render_monthly_chart(message) {
    let container = document.getElementById("mpit-whatif-charts");
    if (!container) return;

    container.innerHTML = "";

    if (message.monthly_chart) {
        let chart_data = message.monthly_chart;

        let col = document.createElement("div");
        col.className = "col-md-12 mb-4";

        let card = document.createElement("div");
        card.className = "frappe-card p-4";

        let title = document.createElement("h6");
        title.className = "mb-3";
        title.innerText = chart_data.title || __("Monthly Distribution");
        card.appendChild(title);

        let chartDiv = document.createElement("div");
        chartDiv.id = "mpit-whatif-monthly-chart";
        chartDiv.style.height = "300px";
        card.appendChild(chartDiv);

        col.appendChild(card);
        container.appendChild(col);

        // Render chart
        new frappe.Chart(chartDiv, {
            data: chart_data.data,
            type: chart_data.type || "bar",
            height: 280,
            colors: chart_data.colors || ["#5e64ff", "#7c3aed"],
            barOptions: {
                spaceRatio: 0.3
            },
            axisOptions: {
                xAxisMode: "tick"
            }
        });
    }
}

function style_report_rows() {
    // Add visual separation for different row types
    setTimeout(function () {
        let rows = document.querySelectorAll(".dt-row");
        rows.forEach(function (row) {
            let cells = row.querySelectorAll(".dt-cell__content");
            cells.forEach(function (cell) {
                if (cell.innerText === "Cost Center Breakdown") {
                    row.style.backgroundColor = "var(--bg-light-gray)";
                    row.style.fontWeight = "bold";
                }
            });
        });
    }, 100);
}

function format_number(value) {
    // Simple number formatting to avoid recursion with frappe.format
    if (value == null || isNaN(value)) return "0";
    return new Intl.NumberFormat(frappe.boot.lang || 'en', {
        style: 'currency',
        currency: frappe.boot.sysdefaults.currency || 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);
}
