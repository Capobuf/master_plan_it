# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt
"""
Backfill monthly_amount_net on existing MPIT Contract records.

Uses the same cadence rules as the controller:
- Spread: net / spread_months
- Rate schedule: no single monthly amount (left empty)
- Billing Monthly/Quarterly/Annual/Other: normalized from current_amount_net

Run via migrate (patches.txt) or manually:
bench --site <site> execute master_plan_it.patches.v2_0.populate_contract_monthly_amount.execute
"""

from __future__ import annotations

import frappe
from frappe.utils import flt

from master_plan_it import mpit_user_prefs, tax


def execute():
	"""Populate monthly_amount_net for existing contracts."""
	frappe.reload_doc("master_plan_it", "doctype", "mpit_contract")
	if not frappe.db.has_column("MPIT Contract", "monthly_amount_net"):
		return

	contracts = frappe.get_all(
		"MPIT Contract",
		fields=[
			"name",
			"current_amount",
			"current_amount_net",
			"current_amount_includes_vat",
			"vat_rate",
			"billing_cycle",
			"spread_months",
		],
	)
	if not contracts:
		return

	contracts_with_rates = set(frappe.get_all("MPIT Contract Rate", pluck="parent"))
	default_vat = mpit_user_prefs.get_default_vat_rate(frappe.session.user)

	updated = 0
	for contract in contracts:
		if contract.name in contracts_with_rates:
			monthly_net = None
		else:
			net = contract.current_amount_net
			if net is None and contract.current_amount is not None:
				final_vat_rate = tax.validate_strict_vat(
					contract.current_amount,
					contract.vat_rate,
					default_vat,
					field_label=frappe._("Current Amount"),
				)
				net, _, _ = tax.split_net_vat_gross(
					contract.current_amount,
					final_vat_rate,
					bool(contract.current_amount_includes_vat),
				)

			monthly_net = _compute_monthly_net(
				net,
				contract.billing_cycle,
				contract.spread_months,
			)

		frappe.db.set_value(
			"MPIT Contract",
			contract.name,
			"monthly_amount_net",
			monthly_net,
			update_modified=False,
		)
		updated += 1

	frappe.db.commit()
	print(f"Updated monthly_amount_net for {updated} MPIT Contract records.")


def _compute_monthly_net(net: float | None, billing_cycle: str | None, spread_months: float | None) -> float | None:
	"""Compute monthly net amount mirroring the controller logic."""
	if net is None:
		return None

	if spread_months:
		months = flt(spread_months or 0)
		if months <= 0:
			return None
		return flt(net, 2) / months

	billing = billing_cycle or "Monthly"
	if billing == "Quarterly":
		return flt(net * 4 / 12, 2)
	if billing == "Annual":
		return flt(net / 12, 2)
	return flt(net, 2)
