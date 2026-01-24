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
		// Add button to create new Planned Item linked to this project
		if (!frm.is_new()) {
			frm.add_custom_button(__("Add Planned Item"), () => {
				frappe.new_doc("MPIT Planned Item", {
					project: frm.doc.name
				});
			}, __("Actions"));

			// Add button to recalculate financial totals
			frm.add_custom_button(__("Recalculate Totals"), async () => {
				frappe.show_alert({ message: __("Recalculating..."), indicator: "blue" });
				await frm.save();
				frappe.show_alert({ message: __("Totals recalculated"), indicator: "green" });
			}, __("Actions"));
		}

		// Show approval hint when in Proposed state
		if (frm.doc.workflow_state === "Proposed" && !frm.is_new()) {
			await master_plan_it.project.show_approval_hint(frm);
		}

		await master_plan_it.project.render_financial_summary(frm);
	},
});

master_plan_it.project.show_approval_hint =
	master_plan_it.project.show_approval_hint ||
	async function (frm) {
		const res = await frappe.call({
			method: "frappe.client.get_count",
			args: {
				doctype: "MPIT Planned Item",
				filters: {
					project: frm.doc.name,
					workflow_state: "Submitted"
				}
			}
		});

		const submitted_count = res.message || 0;

		if (submitted_count === 0) {
			frm.set_intro(
				__("Per approvare questo progetto, è necessario avere almeno una Voce Pianificata in stato <b>Submitted</b>. Usa il pulsante \"Add Planned Item\" per creare una nuova voce."),
				"yellow"
			);
		} else {
			frm.set_intro(
				__("Progetto pronto per l'approvazione: {0} Voce/i Pianificata/e in stato Submitted.", [submitted_count]),
				"green"
			);
		}
	};

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
		const show_quoted = quoted > 0;

		const html = `
			<div class="mpit-project-summary">
				<table class="table table-bordered" style="margin-bottom: 0">
					<thead>
						<tr>
							<th>${__("Planned")}</th>
							${show_quoted ? `<th>${__("Quoted (Approved)")}</th>` : ""}
							<th>${__("Verified Exceptions")}</th>
							<th>${__("Expected (Plan + Exceptions)")}</th>
							<th>${__("Delta vs Planned")}</th>
							${show_quoted ? `<th>${__("Delta vs Quoted")}</th>` : ""}
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>${format_currency(planned)}</td>
							${show_quoted ? `<td>${format_currency(quoted)}</td>` : ""}
							<td>${format_currency(exceptions)}</td>
							<td>${format_currency(expected)}</td>
							<td class="${delta_vs_planned > 0.01 ? "text-danger" : "text-success"}">${format_currency(delta_vs_planned)}</td>
							${show_quoted ? `<td class="${delta_vs_quoted > 0.01 ? "text-danger" : "text-success"}">${format_currency(delta_vs_quoted)}</td>` : ""}
						</tr>
					</tbody>
				</table>
			</div>
		`;

		summary_field.$wrapper.html(html);
	};
