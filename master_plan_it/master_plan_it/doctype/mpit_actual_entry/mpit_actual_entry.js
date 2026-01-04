// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.provide("master_plan_it.vat");

master_plan_it.vat.defaults_promise =
	master_plan_it.vat.defaults_promise ||
	frappe.call({ method: "master_plan_it.mpit_defaults.get_vat_defaults" }).then((r) => r.message || {});

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

const set_planned_item_query = (frm) => {
	frm.set_query("planned_item", function () {
		const filters = {};
		if (frm.doc.project) {
			filters.project = frm.doc.project;
		}
		return { filters: filters };
	});
};

const handle_contract_change = async (frm) => {
	if (frm.doc.contract) {
		// Auto-fetch details if contract changes
		const contract_details = await frappe.db.get_value("MPIT Contract", frm.doc.contract, ["planned_item", "cost_center"]);
		if (contract_details && contract_details.message) {
			const { planned_item, cost_center } = contract_details.message;
			if (planned_item && !frm.doc.planned_item) {
				frm.set_value("planned_item", planned_item);
			}
			// Cost center is auto-fetched by python on save
		}
	}
};

const sync_entry_kind_with_links = async (frm, force = false) => {
	if (!force && !frm.is_new() && !frm.is_dirty()) {
		return;
	}

	const has_link = !!frm.doc.contract || !!frm.doc.project;
	const current = frm.doc.entry_kind;

	if (has_link && current === "Allowance Spend") {
		await frm.set_value("entry_kind", "Delta");
		return true;
	}
	return false;
};


frappe.ui.form.on("MPIT Actual Entry", {
	setup(frm) {
		set_planned_item_query(frm);
	},
	async onload(frm) {
		await master_plan_it.vat.apply_defaults_for_actual(frm);
	},
	async refresh(frm) {
		await master_plan_it.vat.apply_defaults_for_actual(frm);
		await sync_entry_kind_with_links(frm, true);
	},
	async contract(frm) {
		await handle_contract_change(frm);
		await sync_entry_kind_with_links(frm, true);
	},
	async project(frm) {
		set_planned_item_query(frm);
		await sync_entry_kind_with_links(frm, true);
	},
	async posting_date(frm) {
		if (frm.doc.posting_date) {
			const r = await frappe.call({
				method: "master_plan_it.master_plan_it.doctype.mpit_actual_entry.mpit_actual_entry.get_mpit_year",
				args: { posting_date: frm.doc.posting_date }
			});
			if (r && r.message) {
				frm.set_value("year", r.message);
			} else {
				frm.set_value("year", null);
			}
		} else {
			frm.set_value("year", null);
		}
	},
});
