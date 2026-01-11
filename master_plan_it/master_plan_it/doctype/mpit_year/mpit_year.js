// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.ui.form.on("MPIT Year", {
	year: function(frm) {
		if (frm.doc.year) {
			const year = frm.doc.year;
			
			// Auto-fill start_date if empty
			if (!frm.doc.start_date) {
				frm.set_value("start_date", `${year}-01-01`);
			}
			
			// Auto-fill end_date if empty
			if (!frm.doc.end_date) {
				frm.set_value("end_date", `${year}-12-31`);
			}
		}
	}
});
