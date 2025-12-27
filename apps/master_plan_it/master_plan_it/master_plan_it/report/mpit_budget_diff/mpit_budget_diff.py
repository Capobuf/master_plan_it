from __future__ import annotations

import frappe
from frappe import _


def execute(filters=None):
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)
	filters = frappe._dict(filters or {})

	_validate_filters(filters)
	group_by = (filters.get("group_by") or "Category+Vendor").strip()
	only_changed = frappe.utils.cint(filters.get("only_changed", 1))

	budget_a = filters.budget_a
	budget_b = filters.budget_b

	data = _build_rows(budget_a, budget_b, group_by, only_changed)
	columns = _build_columns()
	summary = _build_summary(data)

	return columns, data, None, None, summary


def _validate_filters(filters) -> None:
	if not filters.get("budget_a"):
		frappe.throw(_("budget_a is required"))
	if not filters.get("budget_b"):
		frappe.throw(_("budget_b is required"))
	if filters.budget_a == filters.budget_b:
		frappe.throw(_("budget_a and budget_b must be different budgets"))
	if filters.get("group_by") and filters.get("group_by") not in {"Category+Vendor", "Category"}:
		frappe.throw(_("group_by must be either 'Category+Vendor' or 'Category'"))


def _build_columns() -> list[dict]:
	return [
		{"label": _("Category"), "fieldname": "category", "fieldtype": "Link", "options": "MPIT Category", "width": 200},
		{"label": _("Vendor"), "fieldname": "vendor", "fieldtype": "Link", "options": "MPIT Vendor", "width": 180},
		{"label": _("Budget A Annual Net"), "fieldname": "budget_a_annual_net", "fieldtype": "Currency", "width": 140},
		{"label": _("Budget B Annual Net"), "fieldname": "budget_b_annual_net", "fieldtype": "Currency", "width": 140},
		{"label": _("Delta Annual (B - A)"), "fieldname": "delta_annual", "fieldtype": "Currency", "width": 150},
		{"label": _("Budget A Monthly Eq"), "fieldname": "budget_a_monthly_eq", "fieldtype": "Currency", "width": 140},
		{"label": _("Budget B Monthly Eq"), "fieldname": "budget_b_monthly_eq", "fieldtype": "Currency", "width": 140},
		{"label": _("Delta Monthly Eq"), "fieldname": "delta_monthly", "fieldtype": "Currency", "width": 130},
	]


def _load_budget_totals(budget: str, group_by: str) -> dict:
	fields = [
		"category",
		"SUM(COALESCE(annual_net, amount_net, amount)) AS planned",
	]
	group_fields = ["category"]
	if group_by == "Category+Vendor":
		fields.insert(1, "vendor")
		group_fields.append("vendor")

	rows = frappe.db.sql(
		f"""
		SELECT {", ".join(fields)}
		FROM `tabMPIT Budget Line`
		WHERE parent = %(budget)s
		GROUP BY {", ".join(group_fields)}
		""",
		{"budget": budget},
		as_dict=True,
	)

	result = {}
	for row in rows:
		key = (row.get("category"), row.get("vendor") if group_by == "Category+Vendor" else None)
		result[key] = float(row.get("planned") or 0)
	return result


def _build_rows(budget_a: str, budget_b: str, group_by: str, only_changed: int) -> list[dict]:
	a_map = _load_budget_totals(budget_a, group_by)
	b_map = _load_budget_totals(budget_b, group_by)
	keys = set(a_map.keys()) | set(b_map.keys())

	rows: list[dict] = []
	total_a = total_b = 0.0

	for key in sorted(keys, key=lambda k: (str(k[0] or ""), str(k[1] or ""))):
		category, vendor = key
		planned_a = float(a_map.get(key, 0) or 0)
		planned_b = float(b_map.get(key, 0) or 0)
		delta_annual = planned_b - planned_a

		if only_changed and abs(delta_annual) < 0.0001:
			continue

		monthly_a = planned_a / 12.0
		monthly_b = planned_b / 12.0
		delta_monthly = monthly_b - monthly_a

		rows.append({
			"category": category,
			"vendor": vendor,
			"budget_a_annual_net": planned_a,
			"budget_b_annual_net": planned_b,
			"delta_annual": delta_annual,
			"budget_a_monthly_eq": monthly_a,
			"budget_b_monthly_eq": monthly_b,
			"delta_monthly": delta_monthly,
		})

		total_a += planned_a
		total_b += planned_b

	if rows:
		delta_total = total_b - total_a
		rows.append({
			"category": _("Total"),
			"vendor": "",
			"budget_a_annual_net": total_a,
			"budget_b_annual_net": total_b,
			"delta_annual": delta_total,
			"budget_a_monthly_eq": total_a / 12.0,
			"budget_b_monthly_eq": total_b / 12.0,
			"delta_monthly": delta_total / 12.0,
			"is_total_row": 1,
		})

	return rows


def _build_summary(rows: list[dict]) -> list[dict]:
	if not rows:
		return []

	totals = [r for r in rows if r.get("is_total_row")]
	if not totals:
		return []

	total = totals[0]
	return [
		{
			"label": _("Budget A"),
			"value": frappe.utils.fmt_money(total.get("budget_a_annual_net", 0)),
			"indicator": "blue",
		},
		{
			"label": _("Budget B"),
			"value": frappe.utils.fmt_money(total.get("budget_b_annual_net", 0)),
			"indicator": "blue",
		},
		{
			"label": _("Delta (B - A)"),
			"value": frappe.utils.fmt_money(total.get("delta_annual", 0)),
			"indicator": "green" if (total.get("delta_annual", 0) or 0) >= 0 else "red",
		},
	]
