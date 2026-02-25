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
		frm.set_intro(""); // Clear previous intro
		frm.__is_year_closed = false;

		// === LIVE BUDGET UI ===
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

			frm.add_custom_button(__("Refresh from Sources"), async function () {
				await frm.trigger("refresh_from_sources");
			}, __("Actions"));

			// Check if year is closed
			if (frm.doc.year) {
				frappe.call({
					method: "master_plan_it.annualization.get_year_bounds",
					args: { year: frm.doc.year },
					callback: function (r) {
						if (r.message) {
							const year_end = frappe.datetime.str_to_obj(r.message[1]);
							const today = frappe.datetime.now_date();
							if (frappe.datetime.str_to_obj(today) > year_end) {
								frm.__is_year_closed = true;
								frm.set_intro(
									__("Year closed: auto-refresh is OFF. Manual refresh may modify historical data."),
									"yellow"
								);
							}
						}
					},
				});
			}
		}

		// === SNAPSHOT BUDGET UI ===
		if (frm.doc.budget_type === "Snapshot" && !frm.is_new()) {
			// Help text explaining Snapshot philosophy
			if (frm.doc.workflow_state === "Draft") {
				frm.set_intro(
					__("Snapshot Draft: You can add manual lines (Contract, Project, etc.) for what-if scenarios. ") +
					__("These lines capture values at insertion time and do not auto-update. ") +
					__("Use 'Sync Manual Lines' to refresh values from linked documents."),
					"blue"
				);

				// Sync Manual Lines button - only for Draft Snapshots
				frm.add_custom_button(__("Sync Manual Lines"), async function () {
					await frm.trigger("sync_manual_lines");
				}, __("Actions"));
			} else {
				frm.set_intro(
					__("Snapshot {0}: This budget is read-only. To make changes, create a new Snapshot from the Live budget.").replace("{0}", frm.doc.workflow_state),
					"green"
				);
			}
		}
	},

	/**
	 * Sync manual lines (is_generated=0) with their linked source documents.
	 * Re-fetches cost_center, description, amount from Project or Contract.
	 * Reports deleted/missing source documents.
	 */
	async sync_manual_lines(frm) {
		if (frm.is_dirty()) {
			await frm.save();
		}

		const manual_lines = (frm.doc.lines || []).filter(line => !line.is_generated && (line.project || line.contract));
		if (!manual_lines.length) {
			frappe.msgprint(__("No manual lines with linked Project or Contract to sync."));
			return;
		}

		let synced = 0;
		let not_found = [];
		let errors = [];

		for (const line of manual_lines) {
			try {
				if (line.project) {
					const exists = await frappe.db.exists("MPIT Project", line.project);
					if (!exists) {
						not_found.push(__("Row {0}: Project '{1}' not found (deleted?)").replace("{0}", line.idx).replace("{1}", line.project));
						continue;
					}
					const r = await frappe.db.get_value("MPIT Project", line.project,
						["cost_center", "title", "expected_total_net"]);
					if (r) {
						frappe.model.set_value(line.doctype, line.name, {
							cost_center: r.cost_center || line.cost_center,
							description: r.title || line.description,
							monthly_amount: (r.expected_total_net != null) ? r.expected_total_net : line.monthly_amount,
							recurrence_rule: "None"
						});
						synced++;
					}
				} else if (line.contract) {
					const exists = await frappe.db.exists("MPIT Contract", line.contract);
					if (!exists) {
						not_found.push(__("Row {0}: Contract '{1}' not found (deleted?)").replace("{0}", line.idx).replace("{1}", line.contract));
						continue;
					}
					const r = await frappe.db.get_value("MPIT Contract", line.contract,
						["cost_center", "description", "current_term_monthly_net", "current_term_billing_cycle"]);
					if (r) {
						const recurrence = (r.current_term_billing_cycle === "Other")
							? "None"
							: (r.current_term_billing_cycle || "Monthly");
						frappe.model.set_value(line.doctype, line.name, {
							cost_center: r.cost_center || line.cost_center,
							description: r.description || line.description,
							monthly_amount: r.current_term_monthly_net || line.monthly_amount,
							recurrence_rule: recurrence
						});
						synced++;
					}
				}
			} catch (e) {
				errors.push(__("Row {0}: Error syncing - {1}").replace("{0}", line.idx).replace("{1}", e.message || e));
			}
		}

		// Build summary message
		let msg_parts = [];
		if (synced > 0) {
			msg_parts.push(__("{0} line(s) synced successfully.").replace("{0}", synced));
		}
		if (not_found.length > 0) {
			msg_parts.push("<br><br><b>" + __("Missing documents:") + "</b><ul><li>" + not_found.join("</li><li>") + "</li></ul>");
		}
		if (errors.length > 0) {
			msg_parts.push("<br><b>" + __("Errors:") + "</b><ul><li>" + errors.join("</li><li>") + "</li></ul>");
		}

		if (msg_parts.length > 0) {
			frappe.msgprint({
				title: __("Sync Manual Lines"),
				indicator: not_found.length > 0 || errors.length > 0 ? "orange" : "green",
				message: msg_parts.join("")
			});
		}

		if (synced > 0) {
			frm.dirty();
			frappe.show_alert({ message: __("Don't forget to save!"), indicator: "blue" });
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

		// For closed years, require explicit acknowledgment before refresh
		if (frm.__is_year_closed) {
			return new Promise((resolve) => {
				const d = new frappe.ui.Dialog({
					title: __("Refresh manual su anno chiuso"),
					fields: [
						{
							fieldname: "ack",
							fieldtype: "Check",
							label: __("I understand: manual refresh on a closed year may alter historical data."),
						},
						{
							fieldname: "reason",
							fieldtype: "Small Text",
							label: __("Reason (optional)"),
						},
					],
					primary_action_label: __("Proceed"),
					primary_action: async (values) => {
						if (!values.ack) {
							frappe.msgprint(__("Refresh cancelled: confirmation is required."));
							d.hide();
							resolve();
							return;
						}
						d.hide();

						// Execute refresh with reason
						await frm.call("refresh_from_sources", {
							is_manual: 1,
							reason: values.reason || ""
						});
						await frm.reload_doc();
						frappe.msgprint(__("Budget refreshed from sources."));
						resolve();
					},
				});
				d.show();
			});
		}

		// For non-closed years, refresh directly
		await frm.call("refresh_from_sources", { is_manual: 1 });
		await frm.reload_doc();
		frappe.msgprint(__("Budget refreshed from sources."));
	},
});

/**
 * Event handlers for MPIT Budget Line child table.
 * Auto-populate cost_center when project or contract is selected.
 */
frappe.ui.form.on("MPIT Budget Line", {
	project: function (frm, cdt, cdn) {
		const row = locals[cdt][cdn];
		if (row.project) {
			frappe.db.get_value("MPIT Project", row.project, ["cost_center", "title", "expected_total_net"], (r) => {
				if (r) {
					if (r.cost_center && !row.cost_center) {
						frappe.model.set_value(cdt, cdn, "cost_center", r.cost_center);
					}
					if (r.title && !row.description) {
						frappe.model.set_value(cdt, cdn, "description", r.title);
					}
					// Auto-populate amount from Project's expected total (flat/one-time expense)
					if (r.expected_total_net && !row.monthly_amount) {
						frappe.model.set_value(cdt, cdn, "monthly_amount", r.expected_total_net);
						frappe.model.set_value(cdt, cdn, "recurrence_rule", "None");
					}
				}
			});
		}
	},
	contract: function (frm, cdt, cdn) {
		const row = locals[cdt][cdn];
		if (row.contract) {
			frappe.db.get_value("MPIT Contract", row.contract,
				["cost_center", "description", "current_term_monthly_net", "current_term_billing_cycle"],
				(r) => {
					if (r) {
						if (r.cost_center && !row.cost_center) {
							frappe.model.set_value(cdt, cdn, "cost_center", r.cost_center);
						}
						if (r.description && !row.description) {
							frappe.model.set_value(cdt, cdn, "description", r.description);
						}
						// Auto-populate amount from Contract's current term
						if (r.current_term_monthly_net && !row.monthly_amount) {
							frappe.model.set_value(cdt, cdn, "monthly_amount", r.current_term_monthly_net);
							// Map billing_cycle to recurrence_rule (Other → None for flat amounts)
							const recurrence = (r.current_term_billing_cycle === "Other")
								? "None"
								: (r.current_term_billing_cycle || "Monthly");
							frappe.model.set_value(cdt, cdn, "recurrence_rule", recurrence);
						}
					}
				});
		}
	},
});
