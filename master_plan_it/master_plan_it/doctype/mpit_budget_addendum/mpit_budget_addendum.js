/**
 * FILE: master_plan_it/doctype/mpit_budget_addendum/mpit_budget_addendum.js
 * SCOPO: Filtra Reference Snapshot su soli Snapshot(APP) approvati e (se valorizzato) stesso Year.
 * INPUT: Form MPIT Budget Addendum (year, reference_snapshot).
 * OUTPUT/SIDE EFFECTS: Limita le opzioni del Link field reference_snapshot.
 */

frappe.ui.form.on("MPIT Budget Addendum", {
	setup(frm) {
		frm.set_query("reference_snapshot", function () {
			const filters = {
				budget_type: "Snapshot",
				docstatus: ["!=", 2],
			};
			if (frm.doc.year) {
				filters.year = frm.doc.year;
			}
			return { filters };
		});
	},
});
