// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.provide("master_plan_it.vat");

master_plan_it.vat.defaults_promise =
	master_plan_it.vat.defaults_promise ||
	frappe.call({ method: "master_plan_it.mpit_user_prefs.get_vat_defaults" }).then((r) => r.message || {});

master_plan_it.vat.apply_defaults_for_amendment_line =
	master_plan_it.vat.apply_defaults_for_amendment_line ||
	async function (cdt, cdn) {
		const row = frappe.get_doc(cdt, cdn);
		if (!row || row.__islocal === false || row.__vat_defaults_applied) {
			return;
		}

		const defaults = await master_plan_it.vat.defaults_promise;
		const updates = {};

		if (defaults.default_includes_vat !== undefined && defaults.default_includes_vat !== null) {
			updates.delta_amount_includes_vat = defaults.default_includes_vat ? 1 : 0;
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

frappe.ui.form.on("MPIT Budget Amendment", {
	async lines_add(_frm, cdt, cdn) {
		await master_plan_it.vat.apply_defaults_for_amendment_line(cdt, cdn);
	},
});
