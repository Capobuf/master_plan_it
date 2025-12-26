// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

let mpitContractVatDefaults;

const fetchContractVatDefaults = () => {
	if (!mpitContractVatDefaults) {
		mpitContractVatDefaults = frappe.call({
			method: "master_plan_it.mpit_user_prefs.get_vat_defaults",
		}).then((r) => r.message || {});
	}
	return mpitContractVatDefaults;
};

const applyVatDefaultsToContract = async (frm) => {
	if (!frm.is_new() || frm.__vat_defaults_applied) {
		return;
	}

	const defaults = await fetchContractVatDefaults();
	const updates = {};

	if (defaults.default_includes_vat !== undefined && defaults.default_includes_vat !== null) {
		updates.current_amount_includes_vat = defaults.default_includes_vat ? 1 : 0;
	}

	if (
		(defaults.default_vat_rate || defaults.default_vat_rate === 0) &&
		(frm.doc.vat_rate === undefined || frm.doc.vat_rate === null)
	) {
		updates.vat_rate = defaults.default_vat_rate;
	}

	if (Object.keys(updates).length) {
		await frm.set_value(updates);
	}

	frm.__vat_defaults_applied = true;
};

frappe.ui.form.on("MPIT Contract", {
	async onload(frm) {
		await applyVatDefaultsToContract(frm);
	},
});
