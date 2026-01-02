// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.provide("master_plan_it.vat");

master_plan_it.vat.defaults_promise =
	master_plan_it.vat.defaults_promise ||
	frappe.call({ method: "master_plan_it.mpit_user_prefs.get_vat_defaults" }).then((r) => r.message || {});

master_plan_it.vat.apply_defaults_for_contract =
	master_plan_it.vat.apply_defaults_for_contract ||
	async function (frm) {
		if (!frm.is_new() || frm.doc.__vat_defaults_applied) {
			return;
		}

		const defaults = await master_plan_it.vat.defaults_promise;
		const updates = {};

		if (defaults.default_includes_vat !== undefined && defaults.default_includes_vat !== null) {
			updates.current_amount_includes_vat = defaults.default_includes_vat ? 1 : 0;
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

const toggle_contract_layout = (frm) => {
	const has_spread = !!frm.doc.spread_months;
	const has_rate_rows = Array.isArray(frm.doc.rate_schedule) && frm.doc.rate_schedule.length > 0;

	frm.toggle_display("billing_cycle", !has_spread);
	frm.toggle_display("section_rate_schedule", !has_spread);
	// Spread visible when no rate schedule or when using spread
	frm.toggle_display("section_spread", !has_rate_rows || has_spread);
};

const maybe_autofill_next_renewal_date = async (frm) => {
	if (!frm.doc.auto_renew) {
		return;
	}
	if (frm.doc.next_renewal_date) {
		return;
	}
	if (!frm.doc.end_date) {
		return;
	}
	await frm.set_value("next_renewal_date", frm.doc.end_date);
};

frappe.ui.form.on("MPIT Contract", {
	async onload(frm) {
		await master_plan_it.vat.apply_defaults_for_contract(frm);
		toggle_contract_layout(frm);
		await maybe_autofill_next_renewal_date(frm);
	},
	async refresh(frm) {
		await master_plan_it.vat.apply_defaults_for_contract(frm);
		toggle_contract_layout(frm);
		await maybe_autofill_next_renewal_date(frm);
	},
	async auto_renew(frm) {
		await maybe_autofill_next_renewal_date(frm);
	},
	async end_date(frm) {
		await maybe_autofill_next_renewal_date(frm);
	},
	spread_months(frm) {
		toggle_contract_layout(frm);
	},
	rate_schedule_add(frm) {
		toggle_contract_layout(frm);
	},
	rate_schedule_remove(frm) {
		toggle_contract_layout(frm);
	},
});
