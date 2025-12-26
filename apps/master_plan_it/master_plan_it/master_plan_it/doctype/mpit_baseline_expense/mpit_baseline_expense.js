frappe.provide("master_plan_it.baseline_vat");

// Idempotent helpers (avoid duplicate declarations if script reloads)
if (!master_plan_it.baseline_vat.fetchDefaults) {
	master_plan_it.baseline_vat.fetchDefaults = () => {
		if (!master_plan_it.baseline_vat._promise) {
			master_plan_it.baseline_vat._promise = frappe
				.call({ method: "master_plan_it.mpit_user_prefs.get_vat_defaults" })
				.then((r) => r.message || {});
		}
		return master_plan_it.baseline_vat._promise;
	};
}

const applyVatDefaultsToBaseline = async (frm) => {
	// Apply only for new docs and only once per form load
	if (!frm.is_new() || frm.doc.__vat_defaults_applied) {
		return;
	}

	const defaults = await master_plan_it.baseline_vat.fetchDefaults();
	const updates = {};

	const includesUnset =
		frm.doc.amount_includes_vat === undefined ||
		frm.doc.amount_includes_vat === null;
	if (
		defaults.default_includes_vat !== undefined &&
		defaults.default_includes_vat !== null &&
		includesUnset
	) {
		updates.amount_includes_vat = defaults.default_includes_vat ? 1 : 0;
	}

	const vatUnset =
		frm.doc.vat_rate === undefined ||
		frm.doc.vat_rate === null ||
		frm.doc.vat_rate === "";
	if ((defaults.default_vat_rate || defaults.default_vat_rate === 0) && vatUnset) {
		updates.vat_rate = defaults.default_vat_rate;
	}

	if (Object.keys(updates).length) {
		await frm.set_value(updates);
	}

	frm.doc.__vat_defaults_applied = true;
};

frappe.ui.form.on("MPIT Baseline Expense", {
	async onload(frm) {
		await applyVatDefaultsToBaseline(frm);
	},
	async refresh(frm) {
		await applyVatDefaultsToBaseline(frm);
	},
});
