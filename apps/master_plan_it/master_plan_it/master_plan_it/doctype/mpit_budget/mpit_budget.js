// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

let mpitBudgetVatDefaults;

const fetchBudgetVatDefaults = () => {
	if (!mpitBudgetVatDefaults) {
		mpitBudgetVatDefaults = frappe.call({
			method: "master_plan_it.mpit_user_prefs.get_vat_defaults",
		}).then((r) => r.message || {});
	}
	return mpitBudgetVatDefaults;
};

const applyVatDefaultsToBudgetLine = async (cdt, cdn) => {
	const defaults = await fetchBudgetVatDefaults();
	const row = frappe.get_doc(cdt, cdn);
	if (!row || row.__islocal === false) {
		return;
	}

	const updates = {};
	if (defaults.default_includes_vat !== undefined && defaults.default_includes_vat !== null) {
		const includes = defaults.default_includes_vat ? 1 : 0;
		if (row.amount_includes_vat !== includes) {
			updates.amount_includes_vat = includes;
		}
	}

	if (
		(defaults.default_vat_rate || defaults.default_vat_rate === 0) &&
		(row.vat_rate === undefined || row.vat_rate === null)
	) {
		updates.vat_rate = defaults.default_vat_rate;
	}

	if (Object.keys(updates).length) {
		frappe.model.set_value(cdt, cdn, updates);
	}
};

frappe.ui.form.on("MPIT Budget", {
	async lines_add(_frm, cdt, cdn) {
		await applyVatDefaultsToBudgetLine(cdt, cdn);
	},
});
