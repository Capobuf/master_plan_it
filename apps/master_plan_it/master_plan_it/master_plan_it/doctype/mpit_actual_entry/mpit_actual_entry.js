// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.provide("master_plan_it.vat");

master_plan_it.vat.defaults_promise =
	master_plan_it.vat.defaults_promise ||
	frappe.call({ method: "master_plan_it.mpit_user_prefs.get_vat_defaults" }).then((r) => r.message || {});

master_plan_it.vat.apply_defaults_for_actual =
	master_plan_it.vat.apply_defaults_for_actual ||
	async function (frm) {
		if (!frm.is_new() || frm.doc.__vat_defaults_applied) {
			return;
		}

		const defaults = await master_plan_it.vat.defaults_promise;
		const updates = {};

		if (defaults.default_includes_vat !== undefined && defaults.default_includes_vat !== null) {
			updates.amount_includes_vat = defaults.default_includes_vat ? 1 : 0;
		}

		if (
			(defaults.default_vat_rate || defaults.default_vat_rate === 0) &&
			(frm.doc.vat_rate === undefined || frm.doc.vat_rate === null || frm.doc.vat_rate === "")
		) {
			updates.vat_rate = defaults.default_vat_rate;
		}

		if (Object.keys(updates).length) {
			await frm.set_value(updates);
		}

		frm.doc.__vat_defaults_applied = true;
	};

frappe.ui.form.on("MPIT Actual Entry", {
	async onload(frm) {
		await master_plan_it.vat.apply_defaults_for_actual(frm);
	},
	async refresh(frm) {
		await master_plan_it.vat.apply_defaults_for_actual(frm);
	},
});
