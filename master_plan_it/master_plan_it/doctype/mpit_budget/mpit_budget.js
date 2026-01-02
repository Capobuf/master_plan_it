/**
 * FILE: master_plan_it/doctype/mpit_budget/mpit_budget.js
 * SCOPO: Gestisce UI Budget (default IVA su nuove righe, refresh sorgenti per Live).
 * INPUT: Eventi Frappe form (lines_add, pulsante refresh_from_sources).
 * OUTPUT/SIDE EFFECTS: Applica default VAT alle righe nuove, chiama refresh server-side e ricarica il documento con messaggio all’utente.
 */

frappe.provide("master_plan_it.vat");

master_plan_it.vat.defaults_promise =
	master_plan_it.vat.defaults_promise ||
	frappe.call({ method: "master_plan_it.mpit_defaults.get_vat_defaults" }).then((r) => r.message || {});

master_plan_it.vat.apply_defaults_for_budget_line =
	master_plan_it.vat.apply_defaults_for_budget_line ||
	async function (cdt, cdn) {
		const row = frappe.get_doc(cdt, cdn);
		if (!row || row.__islocal === false || row.__vat_defaults_applied) {
			return;
		}

		const defaults = await master_plan_it.vat.defaults_promise;
		const updates = {};

		if (defaults.default_includes_vat !== undefined && defaults.default_includes_vat !== null) {
			updates.amount_includes_vat = defaults.default_includes_vat ? 1 : 0;
		}

		if (
			(defaults.default_vat_rate || defaults.default_vat_rate === 0) &&
			(row.vat_rate === undefined || row.vat_rate === null || row.vat_rate === "")
		) {
			updates.vat_rate = defaults.default_vat_rate;
		}

		if (Object.keys(updates).length) {
			frappe.model.set_value(cdt, cdn, updates);
		}

		row.__vat_defaults_applied = true;
	};

	frappe.ui.form.on("MPIT Budget", {
	refresh(frm) {
		// Show "Create Snapshot" button only for Live budgets
		if (frm.doc.budget_type === "Live" && !frm.is_new()) {
			frm.add_custom_button(__("Create Snapshot"), async function () {
				if (frm.is_dirty()) {
					frappe.msgprint(__("Please save the document first."));
					return;
				}

				frappe.confirm(
					__("This will create an immutable Snapshot (APP) from this Live budget. Continue?"),
					async function () {
						try {
							const r = await frappe.call({
								method: "master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget.create_snapshot",
								args: { source_budget: frm.doc.name },
							});
							if (r.message) {
								frappe.set_route("Form", "MPIT Budget", r.message);
							}
						} catch (e) {
							frappe.msgprint(__("Failed to create snapshot: ") + (e.message || e));
						}
					}
				);
			}, __("Actions"));
		}

		// Show refresh button only for Live budgets
		if (frm.doc.budget_type === "Live" && !frm.is_new()) {
			frm.add_custom_button(__("Refresh from Sources"), async function () {
				await frm.trigger("refresh_from_sources");
			}, __("Actions"));
		}

		// Show warning banner for year-closed budgets
		if (frm.doc.budget_type === "Live" && frm.doc.year) {
			frm.__is_year_closed = false;
			frappe.call({
				method: "master_plan_it.annualization.get_year_bounds",
				args: { year: frm.doc.year },
				callback: function (r) {
					if (r.message) {
						const year_end = frappe.datetime.str_to_obj(r.message[1]);
						const today = frappe.datetime.now_date();
						if (frappe.datetime.str_to_obj(today) > year_end) {
							frm.__is_year_closed = true;
							frm.dashboard.add_comment(
								__("Year closed: auto-refresh is OFF. Manual refresh may modify historical data."),
								"yellow",
								true
							);
						}
					}
				},
			});
		} else {
			frm.__is_year_closed = false;
		}
	},
	async lines_add(_frm, cdt, cdn) {
		await master_plan_it.vat.apply_defaults_for_budget_line(cdt, cdn);
	},
	async refresh_from_sources(frm) {
		// Trigger server-side refresh; works only for Live budgets
		if (frm.is_dirty()) {
			await frm.save();
		}
		const args = { is_manual: 1 };

		if (frm.__is_year_closed) {
			const values = await frappe.prompt(
				[
					{
						fieldname: "ack",
						fieldtype: "Check",
						label: __("I understand: manual refresh on a closed year may alter historical data."),
						reqd: 1,
					},
					{
						fieldname: "reason",
						fieldtype: "Small Text",
						label: __("Reason (optional)"),
					},
				],
				__("Refresh manual su anno chiuso")
			);

			if (!values.ack) {
				frappe.msgprint(__("Refresh cancelled: confirmation is required."));
				return;
			}
			args.reason = values.reason || "";
		}

		await frm.call("refresh_from_sources", args);
		await frm.reload_doc();
		frappe.msgprint(__("Budget refreshed from sources."));
	},
});
