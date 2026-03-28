frappe.ui.form.on("MPIT Project", {
    async refresh(frm) {
        await render_financial_summary(frm);
    },
});

async function render_financial_summary(frm) {
    const summaryField = frm.fields_dict && frm.fields_dict.financial_summary;
    if (!summaryField || !summaryField.$wrapper) {
        return;
    }

    if (!frm.doc.name || frm.doc.__islocal || String(frm.doc.name).startsWith("new-")) {
        summaryField.$wrapper.html("");
        return;
    }

    const response = await frappe.call({
        method: "master_plan_it.master_plan_it.doctype.mpit_project.mpit_project.get_project_financial_summary_data",
        args: { project: frm.doc.name },
    });

    const data = response.message || {};
    const forecast = data.forecast_total_net || 0;
    const actual = data.actual_total_net || 0;
    const variance = data.variance_net || 0;

    const html = `
        <table class="table table-bordered" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th>${__("Forecast")}</th>
                    <th>${__("Actual")}</th>
                    <th>${__("Variance")}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>${frappe.format(forecast, { fieldtype: "Currency" })}</td>
                    <td>${frappe.format(actual, { fieldtype: "Currency" })}</td>
                    <td>${frappe.format(variance, { fieldtype: "Currency" })}</td>
                </tr>
            </tbody>
        </table>
    `;

    summaryField.$wrapper.html(html);
}
