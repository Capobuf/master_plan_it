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
		// When a new term row is added, check if previous terms need to_date updates
		await maybe_update_previous_term_end_dates(frm);
	},

	async from_date(frm, cdt, cdn) {
		// When from_date changes, update previous term's to_date and set default for this term
		await maybe_update_previous_term_end_dates(frm);
		await maybe_set_default_to_date(frm, cdt, cdn);
	},
});

/**
 * Update to_date for previous terms when a new term is added or from_date changes.
 *
 * Logic:
 * - For each term that is not the last one (sorted by from_date):
 *   - If to_date is empty: auto-set to next_term.from_date - 1 day
 *   - If to_date is set but differs from computed: prompt user with frappe.confirm()
 *
 * This ensures continuity between terms while respecting user-entered values.
 */
async function maybe_update_previous_term_end_dates(frm) {
	// Get all terms with from_date, sorted chronologically
	const terms = (frm.doc.terms || [])
		.filter(t => t.from_date)
		.sort((a, b) => new Date(a.from_date) - new Date(b.from_date));

	if (terms.length < 2) {
		return; // No previous terms to update
	}

	// Process all terms except the last one
	for (let i = 0; i < terms.length - 1; i++) {
		const term = terms[i];
		const next_term = terms[i + 1];

		// Compute expected end date: day before next term starts
		const computed_end = frappe.datetime.add_days(next_term.from_date, -1);

		// If to_date is empty, auto-set silently
		if (!term.to_date) {
			frappe.model.set_value(term.doctype, term.name, "to_date", computed_end);
			continue;
		}

		// If to_date is already set to the computed value, no action needed
		if (term.to_date === computed_end) {
			continue;
		}

		// to_date differs from computed - prompt user for confirmation
		const confirmed = await new Promise(resolve => {
			frappe.confirm(
				__("Term ending {0} should end on {1} (day before next term). Update?",
				   [frappe.datetime.str_to_user(term.to_date), frappe.datetime.str_to_user(computed_end)]),
				() => resolve(true),
				() => resolve(false)
			);
		});

		if (confirmed) {
			frappe.model.set_value(term.doctype, term.name, "to_date", computed_end);
		}
	}
}

/**
 * Set default to_date for a term if from_date is set and to_date is empty.
 *
 * Default: from_date + 1 year - 1 day (assumes annual contract terms)
 *
 * This provides a sensible default for new terms while allowing users to change it.
 */
async function maybe_set_default_to_date(frm, cdt, cdn) {
	const row = locals[cdt][cdn];

	if (row.from_date && !row.to_date) {
		// Default: +1 year - 1 day from the from_date
		const default_end = frappe.datetime.add_days(
			frappe.datetime.add_months(row.from_date, 12),
			-1
		);
		frappe.model.set_value(cdt, cdn, "to_date", default_end);
	}
}
