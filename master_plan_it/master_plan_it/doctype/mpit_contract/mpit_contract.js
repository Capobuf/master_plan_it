// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.provide("master_plan_it.vat");

// Fetch VAT defaults once per session (cached promise)
master_plan_it.vat.defaults_promise =
	master_plan_it.vat.defaults_promise ||
	frappe.call({ method: "master_plan_it.mpit_defaults.get_vat_defaults" }).then((r) => r.message || {});

const maybe_autofill_next_renewal_date = async (frm) => {
	if (!frm.doc.auto_renew || frm.doc.next_renewal_date || !frm.doc.end_date) {
		return;
	}
	await frm.set_value("next_renewal_date", frm.doc.end_date);
};

// Apply VAT defaults to new term rows
const apply_term_defaults = async (frm, cdt, cdn) => {
	const defaults = await master_plan_it.vat.defaults_promise;
	const row = locals[cdt][cdn];

	if (!row.__vat_defaults_applied) {
		if (defaults.default_includes_vat !== undefined) {
			frappe.model.set_value(cdt, cdn, "amount_includes_vat", defaults.default_includes_vat ? 1 : 0);
		}
		if (defaults.default_vat_rate !== undefined && !row.vat_rate) {
			frappe.model.set_value(cdt, cdn, "vat_rate", defaults.default_vat_rate);
		}
		row.__vat_defaults_applied = true;
	}
};

frappe.ui.form.on("MPIT Contract", {
	async refresh(frm) {
		await maybe_autofill_next_renewal_date(frm);
	},
	async auto_renew(frm) {
		await maybe_autofill_next_renewal_date(frm);
	},
	async end_date(frm) {
		await maybe_autofill_next_renewal_date(frm);
	},
});

frappe.ui.form.on("MPIT Contract Term", {
	async terms_add(frm, cdt, cdn) {
		await apply_term_defaults(frm, cdt, cdn);
	},
});
