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
					fieldtype: "Data",
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
			method: "master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget.get_cost_center_summary",
			args: {
				year: frm.__mpit_summary_year,
				cost_center: frm.doc.name,
			},
		});
		const data = r.message || {};
		render_fields(frm, data);
		render_dashboard(frm, data);
	} catch (e) {
		console.warn("Failed to load Cost Center summary", e);
	}
}

function render_fields(frm, data) {
	frm.set_value("summary_year", data.year || frm.__mpit_summary_year);
	frm.set_value("plan_amount", data.plan || 0);
	frm.set_value("snapshot_allowance", data.snapshot_allowance || 0);
	frm.set_value("addendum_total", data.addendum_total || 0);
	frm.set_value("cap_total", data.cap_total || 0);
	frm.set_value("actual_amount", data.actual || 0);
	frm.set_value("remaining_amount", data.remaining || 0);
	frm.set_value("over_cap_amount", data.over_cap || 0);
	frm.refresh_field("summary_year");
	frm.refresh_field("plan_amount");
	frm.refresh_field("snapshot_allowance");
	frm.refresh_field("addendum_total");
	frm.refresh_field("cap_total");
	frm.refresh_field("actual_amount");
	frm.refresh_field("remaining_amount");
	frm.refresh_field("over_cap_amount");
}

function render_dashboard(frm, data) {
	frm.dashboard.clear_headline();
	frm.dashboard.set_headline(__("Budget Summary — Year {0}").format([data.year || frm.__mpit_summary_year]));

	const cap = parseFloat(data.cap_total || 0);
	const actual = parseFloat(data.actual || 0);
	const plan = parseFloat(data.plan || 0);
	const snapshot = parseFloat(data.snapshot_allowance || 0);
	const addendum = parseFloat(data.addendum_total || 0);
	const remaining = parseFloat(data.remaining || 0);
	const over = parseFloat(data.over_cap || 0);

	frm.dashboard.add_indicator(
		__("Cap: {0} (Allowance {1} + Addendum {2})").format([
			frappe.format(cap, { fieldtype: "Currency" }),
			frappe.format(snapshot, { fieldtype: "Currency" }),
			frappe.format(addendum, { fieldtype: "Currency" }),
		]),
		cap >= actual ? "green" : "red"
	);
	frm.dashboard.add_indicator(
		__("Actual: {0}").format([frappe.format(actual, { fieldtype: "Currency" })]),
		actual > cap && cap > 0 ? "red" : "blue"
	);
	frm.dashboard.add_indicator(
		__("Plan (Live): {0}").format([frappe.format(plan, { fieldtype: "Currency" })]),
		"orange"
	);
	if (remaining > 0) {
		frm.dashboard.add_indicator(
			__("Remaining: {0}").format([frappe.format(remaining, { fieldtype: "Currency" })]),
			"green"
		);
	}
	if (over > 0) {
		frm.dashboard.add_indicator(
			__("Over Cap: {0}").format([frappe.format(over, { fieldtype: "Currency" })]),
			"red"
		);
	}
}
