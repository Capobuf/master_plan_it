frappe.ui.form.on("MPIT Project", {
    async refresh(frm) {
        if (!frm.is_new()) {
            frm.__mpit_summary_year = frm.__mpit_summary_year || frm.doc.summary_year || String(new Date().getFullYear());
            frm.add_custom_button(__("Change Year"), async () => {
                const r = await frappe.prompt(
                    {
                        fieldname: "year",
                        fieldtype: "Link",
                        options: "MPIT Year",
                        label: __("Year"),
                        reqd: 1,
                        default: frm.__mpit_summary_year,
                    },
                    null,
                    __("Select Year")
                );
                if (r && r.year) {
                    frm.__mpit_summary_year = r.year;
                    await render_financial_summary(frm);
                }
            });
        }

        await render_financial_summary(frm);
    },
});

async function render_financial_summary(frm) {
    const summaryField = frm.fields_dict && frm.fields_dict.financial_summary;
    if (!summaryField || !summaryField.$wrapper) {
        return;
    }

    if (!frm.doc.name || frm.doc.__islocal || String(frm.doc.name).startsWith("new-")) {
        frm.set_value("summary_year", "");
        summaryField.$wrapper.html("");
        return;
    }

    const response = await frappe.call({
        method: "master_plan_it.master_plan_it.doctype.mpit_project.mpit_project.get_project_financial_summary_data",
        args: {
            project: frm.doc.name,
            year: frm.__mpit_summary_year,
        },
    });

    const data = response.message || {};
    const year = data.year || frm.__mpit_summary_year || "";
    const forecast = data.forecast_total_net || 0;
    const actual = data.actual_total_net || 0;
    const variance = data.variance_net || 0;
    const safeYear =
        frappe.utils && typeof frappe.utils.escape_html === "function"
            ? frappe.utils.escape_html(String(year || ""))
            : String(year || "");
    frm.set_value("summary_year", year);

    const html = `
        <div style="margin-bottom: 8px;"><strong>${__("Year")}:</strong> ${safeYear}</div>
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
