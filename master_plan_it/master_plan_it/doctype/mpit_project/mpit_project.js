// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.provide("master_plan_it.vat");
frappe.provide("master_plan_it.project");

master_plan_it.vat.defaults_promise =
	master_plan_it.vat.defaults_promise ||
	frappe.call({ method: "master_plan_it.mpit_defaults.get_vat_defaults" }).then((r) => r.message || {});

master_plan_it.vat.apply_defaults_for_project_allocation =
	master_plan_it.vat.apply_defaults_for_project_allocation ||
	async function (cdt, cdn) {
		const row = frappe.get_doc(cdt, cdn);
		if (!row || row.__islocal === false || row.__vat_defaults_applied) {
			return;
		}

		const defaults = await master_plan_it.vat.defaults_promise;
		const updates = {};

		if (defaults.default_includes_vat !== undefined && defaults.default_includes_vat !== null) {
			updates.planned_amount_includes_vat = defaults.default_includes_vat ? 1 : 0;
		}

		if (
			(defaults.default_vat_rate || defaults.default_vat_rate === 0) &&
			(row.vat_rate === undefined || row.vat_rate === null || row.vat_rate === "")
		) {
			updates.vat_rate = defaults.default_vat_rate;
		}

		if (Object.keys(updates).length) {
			frappe.model.set_value(cdt, cdn, updates);
		}

		row.__vat_defaults_applied = true;
	};

frappe.ui.form.on("MPIT Project", {

	async refresh(frm) {
		await master_plan_it.project.render_financial_summary(frm);
	},
});

master_plan_it.project.render_financial_summary =
	master_plan_it.project.render_financial_summary ||
	async function (frm) {
		const summary_field = frm.fields_dict && frm.fields_dict.financial_summary;
		if (!summary_field || !summary_field.$wrapper) {
			return;
		}

		const planned = frm.doc.planned_total_net || 0;
		const quoted = frm.doc.quoted_total_net || 0;
		const expected_base = quoted > 0 ? quoted : planned;

		let exceptions = 0;
		if (frm.doc.name) {
			const res = await frappe.call({
				method: "master_plan_it.master_plan_it.doctype.mpit_project.mpit_project.get_project_actuals_totals",
				args: { project: frm.doc.name },
			});
			exceptions = (res.message && res.message.actual_total_net) || 0;
		}

		const expected = frm.doc.expected_total_net || expected_base + exceptions;
		const delta_vs_planned = expected - planned;
		const delta_vs_quoted = quoted > 0 ? expected - quoted : expected - planned;

		const format_currency = (value) => frappe.format(value || 0, { fieldtype: "Currency" });
		const html = `
			<div class="mpit-project-summary">
				<table class="table table-bordered" style="margin-bottom: 0">
					<thead>
						<tr>
							<th>${__("Planned")}</th>
							<th>${__("Quoted (Approved)")}</th>
							<th>${__("Verified Exceptions")}</th>
							<th>${__("Expected (Plan + Exceptions)")}</th>
							<th>${__("Delta vs Planned")}</th>
							<th>${__("Delta vs Quoted")}</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>${format_currency(planned)}</td>
							<td>${format_currency(quoted)}</td>
							<td>${format_currency(exceptions)}</td>
							<td>${format_currency(expected)}</td>
							<td class="${delta_vs_planned < 0 ? "text-danger" : "text-success"}">${format_currency(delta_vs_planned)}</td>
							<td class="${delta_vs_quoted < 0 ? "text-danger" : "text-success"}">${format_currency(delta_vs_quoted)}</td>
						</tr>
					</tbody>
				</table>
			</div>
		`;

		summary_field.$wrapper.html(html);
	};
