// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.provide("master_plan_it.vat");

master_plan_it.vat.defaults_promise =
	master_plan_it.vat.defaults_promise ||
	frappe.call({ method: "master_plan_it.mpit_user_prefs.get_vat_defaults" }).then((r) => r.message || {});

master_plan_it.vat.apply_defaults_for_budget_line =
	master_plan_it.vat.apply_defaults_for_budget_line ||
	async function (cdt, cdn) {
		const row = frappe.get_doc(cdt, cdn);
		if (!row || row.__islocal === false || row.__vat_defaults_applied) {
			return;
		}

		const defaults = await master_plan_it.vat.defaults_promise;
		const updates = {};

		if (defaults.default_includes_vat !== undefined && defaults.default_includes_vat !== null) {
			updates.amount_includes_vat = defaults.default_includes_vat ? 1 : 0;
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

frappe.ui.form.on("MPIT Budget", {
	async lines_add(_frm, cdt, cdn) {
		await master_plan_it.vat.apply_defaults_for_budget_line(cdt, cdn);
	},
	async refresh_from_sources(frm) {
		// Trigger server-side refresh; works only for Forecast budgets
		if (frm.is_dirty()) {
			await frm.save();
		}
		await frm.call("refresh_from_sources");
		await frm.reload_doc();
		frappe.msgprint(__("Budget refreshed from sources."));
	},
	async set_active_btn(frm) {
		if (frm.is_dirty()) {
			await frm.save();
		}
		await frappe.call({
			method: "master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget.set_active",
			args: { budget: frm.doc.name },
		});
		await frm.reload_doc();
		frappe.msgprint(__("Budget set as active Forecast."));
	},
});
