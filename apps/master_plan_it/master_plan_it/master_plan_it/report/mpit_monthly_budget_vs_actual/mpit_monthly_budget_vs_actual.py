from __future__ import annotations

import frappe
from frappe import _


def execute(filters=None):
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)
	filters = frappe._dict(filters or {})

	_validate_filters(filters)
	year = int(filters.year)
	include_portfolio = frappe.utils.cint(filters.get("include_portfolio", 1))
	from_month, to_month = _month_range(filters)

	portfolio_categories = _get_portfolio_categories(year) if not include_portfolio else set()
	planned_annual = _get_planned_annual_total(filters, portfolio_categories)
	actual_by_month = _get_actuals_by_month(filters, portfolio_categories)

	rows = []
	planned_cum = actual_cum = 0.0
	variance_cum = 0.0
	planned_month = planned_annual / 12.0

	for month in range(from_month, to_month + 1):
		actual_month = float(actual_by_month.get(month, 0) or 0)
		variance = actual_month - planned_month

		planned_cum += planned_month
		actual_cum += actual_month
		variance_cum = actual_cum - planned_cum

		rows.append({
			"month": f"{year}-{month:02d}",
			"planned": planned_month,
			"actual": actual_month,
			"variance": variance,
			"planned_cumulative": planned_cum,
			"actual_cumulative": actual_cum,
			"variance_cumulative": variance_cum,
		})

	columns = _build_columns()
	summary = _build_summary(planned_cum, actual_cum, variance_cum)

	return columns, rows, None, None, summary


def _validate_filters(filters) -> None:
	if not filters.get("year"):
		frappe.throw(_("year is required"))


def _month_range(filters) -> tuple[int, int]:
	from_month = int(filters.get("from_month") or 1)
	to_month = int(filters.get("to_month") or 12)
	from_month = max(1, min(12, from_month))
	to_month = max(1, min(12, to_month))
	if from_month > to_month:
		from_month, to_month = to_month, from_month
	return from_month, to_month


def _build_columns() -> list[str]:
	return [
		{"label": _("Month"), "fieldname": "month", "fieldtype": "Data", "width": 90},
		{"label": _("Planned"), "fieldname": "planned", "fieldtype": "Currency", "width": 120},
		{"label": _("Actual"), "fieldname": "actual", "fieldtype": "Currency", "width": 120},
		{"label": _("Variance"), "fieldname": "variance", "fieldtype": "Currency", "width": 120},
		{"label": _("Planned Cumulative"), "fieldname": "planned_cumulative", "fieldtype": "Currency", "width": 140},
		{"label": _("Actual Cumulative"), "fieldname": "actual_cumulative", "fieldtype": "Currency", "width": 140},
		{"label": _("Variance Cumulative"), "fieldname": "variance_cumulative", "fieldtype": "Currency", "width": 150},
	]


def _get_planned_annual_total(filters, portfolio_categories: set[str]) -> float:
	params = {"year": filters.year}
	conditions = ["b.docstatus = 1", "b.year = %(year)s"]
	line_filters = []

	if filters.get("category"):
		line_filters.append("bl.category = %(category)s")
		params["category"] = filters.category
	if filters.get("vendor"):
		line_filters.append("bl.vendor = %(vendor)s")
		params["vendor"] = filters.vendor
	if filters.get("project"):
		line_filters.append("bl.project = %(project)s")
		params["project"] = filters.project
	if filters.get("contract"):
		line_filters.append("bl.contract = %(contract)s")
		params["contract"] = filters.contract
	if portfolio_categories:
		line_filters.append("bl.category NOT IN %(portfolio)s")
		params["portfolio"] = tuple(portfolio_categories)

	where = " AND ".join(conditions + line_filters)

	base_rows = frappe.db.sql(
		f"""
		SELECT SUM(COALESCE(bl.annual_net, bl.amount_net, bl.amount)) AS planned
		FROM `tabMPIT Budget` b
		JOIN `tabMPIT Budget Line` bl ON bl.parent = b.name
		WHERE {where}
		""",
		params,
		as_dict=True,
	)
	base_total = float(base_rows[0]["planned"] or 0) if base_rows else 0.0

	amend_conditions = ["ba.docstatus = 1", "b.year = %(year)s"]
	if filters.get("category"):
		amend_conditions.append("al.category = %(category)s")
	if filters.get("vendor"):
		amend_conditions.append("al.vendor = %(vendor)s")
	if filters.get("project"):
		amend_conditions.append("al.project = %(project)s")
	if filters.get("contract"):
		amend_conditions.append("al.contract = %(contract)s")
	if portfolio_categories:
		amend_conditions.append("al.category NOT IN %(portfolio)s")

	amend_where = " AND ".join(amend_conditions)

	amend_rows = frappe.db.sql(
		f"""
		SELECT SUM(COALESCE(al.delta_amount_net, al.delta_amount)) AS delta_planned
		FROM `tabMPIT Budget Amendment` ba
		JOIN `tabMPIT Budget` b ON b.name = ba.budget
		JOIN `tabMPIT Amendment Line` al ON al.parent = ba.name
		WHERE {amend_where}
		""",
		params,
		as_dict=True,
	)
	amend_total = float(amend_rows[0]["delta_planned"] or 0) if amend_rows else 0.0

	return base_total + amend_total


def _get_actuals_by_month(filters, portfolio_categories: set[str]) -> dict[int, float]:
	params = {"year": filters.year}
	conditions = ["year = %(year)s"]

	if filters.get("category"):
		conditions.append("category = %(category)s")
		params["category"] = filters.category
	if filters.get("vendor"):
		conditions.append("vendor = %(vendor)s")
		params["vendor"] = filters.vendor
	if filters.get("project"):
		conditions.append("project = %(project)s")
		params["project"] = filters.project
	if filters.get("contract"):
		conditions.append("contract = %(contract)s")
		params["contract"] = filters.contract
	if portfolio_categories:
		conditions.append("category NOT IN %(portfolio)s")
		params["portfolio"] = tuple(portfolio_categories)

	where = " AND ".join(conditions)

	rows = frappe.db.sql(
		f"""
		SELECT MONTH(posting_date) AS month, SUM(COALESCE(amount_net, amount)) AS actual
		FROM `tabMPIT Actual Entry`
		WHERE {where}
		GROUP BY MONTH(posting_date)
		""",
		params,
		as_dict=True,
	)

	return {int(r["month"]): float(r["actual"] or 0) for r in rows}


def _get_portfolio_categories(year: int) -> set[str]:
	rows = frappe.db.sql(
		"""
		SELECT DISTINCT bl.category
		FROM `tabMPIT Budget` b
		JOIN `tabMPIT Budget Line` bl ON bl.parent = b.name
		WHERE b.docstatus = 1
		  AND b.year = %(year)s
		  AND COALESCE(bl.is_portfolio_bucket, 0) = 1
		""",
		{"year": year},
	)
	return {r[0] for r in rows if r and r[0]}


def _build_summary(planned_cum: float, actual_cum: float, variance_cum: float) -> list[dict]:
	return [
		{
			"label": _("Planned"),
			"value": frappe.utils.fmt_money(planned_cum),
			"indicator": "blue",
		},
		{
			"label": _("Actual"),
			"value": frappe.utils.fmt_money(actual_cum),
			"indicator": "blue",
		},
		{
			"label": _("Variance (Actual - Planned)"),
			"value": frappe.utils.fmt_money(variance_cum),
			"indicator": "green" if (variance_cum or 0) >= 0 else "red",
		},
	]
