/**
 * FILE: master_plan_it/doctype/mpit_budget_addendum/mpit_budget_addendum.js
 * SCOPO: Filtra Reference Snapshot su soli Snapshot(APP) approvati e (se valorizzato) stesso Year.
 * INPUT: Form MPIT Budget Addendum (year, reference_snapshot, cost_center).
 * OUTPUT/SIDE EFFECTS: Limita le opzioni del Link field reference_snapshot e avvisa su edge case.
 */

frappe.ui.form.on("MPIT Budget Addendum", {
	setup(frm) {
		frm.set_query("reference_snapshot", function () {
			const filters = {
				budget_type: "Snapshot",
				docstatus: 1,
			};
			if (frm.doc.year) {
				filters.year = frm.doc.year;
			}
			return { filters };
		});
	},
	refresh(frm) {
		frm.trigger("mpit_check_reference_snapshot");
	},
	year(frm) {
		frm.trigger("mpit_check_reference_snapshot");
	},
	cost_center(frm) {
		frm.trigger("mpit_check_reference_snapshot");
	},
	reference_snapshot(frm) {
		frm.trigger("mpit_check_reference_snapshot");
	},
	async mpit_check_reference_snapshot(frm) {
		if (!frm.doc.reference_snapshot) {
			frm.set_intro("");
			return;
		}

		const warnings = [];
		const ref = frm.doc.reference_snapshot;
		const year = frm.doc.year;
		const cost_center = frm.doc.cost_center;

		try {
			const snapshot = await frappe.db.get_value("MPIT Budget", ref, ["docstatus", "year", "budget_type"]);
			if (!snapshot || !snapshot.message) {
				warnings.push(__("Reference Snapshot not found."));
			} else {
				const meta = snapshot.message;
				if (meta.budget_type !== "Snapshot") {
					warnings.push(__("Reference must be a Snapshot budget."));
				}
				if (meta.docstatus !== 1) {
					warnings.push(__("Snapshot must be approved (docstatus=1)."));
				}
				if (year && meta.year && String(meta.year) !== String(year)) {
					warnings.push(__("Snapshot year does not match Addendum year."));
				}
			}
		} catch (e) {
			warnings.push(__("Unable to verify Snapshot (check permissions)."));
		}

		if (!cost_center) {
			warnings.push(__("Set Cost Center to validate Allowance line."));
		} else {
			try {
				const allowance = await frappe.call({
					method: "frappe.client.get_list",
					args: {
						doctype: "MPIT Budget Line",
						fields: ["name"],
						filters: {
							parent: ref,
							line_kind: "Allowance",
							cost_center: cost_center,
						},
						limit_page_length: 1,
					},
				});
				if (!allowance.message || allowance.message.length === 0) {
					warnings.push(__("Snapshot has no Allowance line for this Cost Center."));
				}
			} catch (e) {
				warnings.push(__("Unable to verify Allowance line (check permissions)."));
			}
		}

		if (warnings.length) {
			frm.set_intro(warnings.join("<br>"), "orange");
		} else {
			frm.set_intro("");
		}
	},
});
