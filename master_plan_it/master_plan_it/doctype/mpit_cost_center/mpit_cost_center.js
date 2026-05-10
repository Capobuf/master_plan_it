frappe.ui.form.on("MPIT Cost Center", {
    async refresh(frm) {
        if (frm.is_new()) {
            return;
        }

        const currentYear = new Date().getFullYear().toString();
        frm.__mpit_summary_year = frm.__mpit_summary_year || currentYear;
        await load_summary(frm);

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
                await load_summary(frm);
            }
        });
    },
});

async function load_summary(frm) {
    try {
        const r = await frappe.call({
            method: "master_plan_it.master_plan_it.doctype.mpit_cost_center.mpit_cost_center.get_cost_center_financial_summary_data",
            args: {
                year: frm.__mpit_summary_year,
                cost_center: frm.doc.name,
            },
        });
        const data = r.message || {};
        render_fields(frm, data);
        render_dashboard(frm, data);
    } catch (e) {
        console.warn("Failed to load cost center financial summary", e);
    }
}

function render_fields(frm, data) {
    frm.set_value("summary_year", data.year || frm.__mpit_summary_year);
    frm.set_value("forecast_total", data.forecast_total || 0);
    frm.set_value("actual_total", data.actual_total || 0);
    frm.set_value("actual_standard", data.actual_standard || 0);
    frm.set_value("actual_on_plafond", data.actual_on_plafond || 0);
    frm.set_value("actual_extra", data.actual_extra || 0);
    frm.set_value("plafond_total", data.plafond || 0);
    frm.set_value("plafond_remaining", data.remaining || 0);
    frm.set_value("plafond_over", data.over || 0);

    [
        "summary_year",
        "forecast_total",
        "actual_total",
        "actual_standard",
        "actual_on_plafond",
        "actual_extra",
        "plafond_total",
        "plafond_remaining",
        "plafond_over",
    ].forEach((fieldname) => frm.refresh_field(fieldname));
}

function render_dashboard(frm, data) {
    frm.dashboard.clear_headline();
    frm.dashboard.set_headline(__("Financial Summary - Year {0}").format([data.year || frm.__mpit_summary_year]));

    const forecast = parseFloat(data.forecast_total || 0);
    const actual = parseFloat(data.actual_total || 0);
    const actualStandard = parseFloat(data.actual_standard || 0);
    const onPlafond = parseFloat(data.actual_on_plafond || 0);
    const extra = parseFloat(data.actual_extra || 0);
    const plafond = parseFloat(data.plafond || 0);
    const plafondConsumed = parseFloat(data.plafond_consumed || 0);
    const remaining = parseFloat(data.remaining || 0);
    const over = parseFloat(data.over || 0);

    frm.dashboard.add_indicator(
        __("Forecast: {0}").format([frappe.format(forecast, { fieldtype: "Currency" })]),
        "blue"
    );
    frm.dashboard.add_indicator(
        __("Actual: {0}").format([frappe.format(actual, { fieldtype: "Currency" })]),
        "orange"
    );
    frm.dashboard.add_indicator(
        __("Actual Standard: {0}").format([frappe.format(actualStandard, { fieldtype: "Currency" })]),
        "blue"
    );
    frm.dashboard.add_indicator(
        __("Plafond: {0}").format([frappe.format(plafond, { fieldtype: "Currency" })]),
        plafondConsumed <= plafond ? "green" : "red"
    );
    frm.dashboard.add_indicator(
        __("Plafond Consumed: {0}").format([frappe.format(plafondConsumed, { fieldtype: "Currency" })]),
        plafondConsumed <= plafond ? "green" : "red"
    );
    frm.dashboard.add_indicator(
        __("Actual On Plafond: {0}").format([frappe.format(onPlafond, { fieldtype: "Currency" })]),
        onPlafond <= plafond ? "green" : "red"
    );
    frm.dashboard.add_indicator(
        __("Extra: {0}").format([frappe.format(extra, { fieldtype: "Currency" })]),
        "orange"
    );

    if (remaining > 0) {
        frm.dashboard.add_indicator(
            __("Remaining Plafond: {0}").format([frappe.format(remaining, { fieldtype: "Currency" })]),
            "green"
        );
    }
    if (over > 0) {
        frm.dashboard.add_indicator(
            __("Over Plafond: {0}").format([frappe.format(over, { fieldtype: "Currency" })]),
            "red"
        );
    }
}
