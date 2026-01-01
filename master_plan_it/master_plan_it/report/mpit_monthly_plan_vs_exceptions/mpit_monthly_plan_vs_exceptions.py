from __future__ import annotations

import frappe
from frappe import _
from master_plan_it import annualization

# Report: Monthly Plan vs Exceptions by cost center/vendor/project/contract.
# Inputs: filters (year required, from_month/to_month optional, cost_center/vendor/project/contract).
# Output: monthly rows with plan vs exceptions, plus cumulative summary.


def execute(filters=None):
	if isinstance(filters, str):
		filters = frappe.parse_json(filters)
	filters = frappe._dict(filters or {})

	_validate_filters(filters)
	year = int(filters.year)
	from_month, to_month = _month_range(filters)

	budget = _resolve_budget(filters)
	plan_by_month = _get_plan_by_month(filters, budget)
	actual_by_month = _get_actuals_by_month(filters)

	rows = []
	planned_cum = actual_cum = 0.0
	variance_cum = 0.0

	for month in range(from_month, to_month + 1):
		planned_month = float(plan_by_month.get(month, 0) or 0)
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
		{"label": _("Plan"), "fieldname": "planned", "fieldtype": "Currency", "width": 120},
		{"label": _("Exceptions / Allowance"), "fieldname": "actual", "fieldtype": "Currency", "width": 140},
		{"label": _("Variance (Exceptions - Plan)"), "fieldname": "variance", "fieldtype": "Currency", "width": 180},
		{"label": _("Plan Cumulative"), "fieldname": "planned_cumulative", "fieldtype": "Currency", "width": 140},
		{"label": _("Exceptions Cumulative"), "fieldname": "actual_cumulative", "fieldtype": "Currency", "width": 150},
		{"label": _("Variance Cumulative"), "fieldname": "variance_cumulative", "fieldtype": "Currency", "width": 160},
	]


def _get_plan_by_month(filters, budget: str | None) -> dict[int, float]:
	if not budget:
		return {}

	params = {"budget": budget}
	year_start, year_end = annualization.get_year_bounds(filters.year)
	conditions = ["bl.parent = %(budget)s", "COALESCE(bl.is_active,1)=1"]

	for field in ("vendor", "project", "contract", "cost_center"):
		if filters.get(field):
			conditions.append(f"bl.{field} = %({field})s")
			params[field] = filters.get(field)

	where = " AND ".join(conditions)

	lines = frappe.db.sql(
		f"""
		SELECT
			bl.period_start_date,
			bl.period_end_date,
			bl.annual_net,
			bl.amount_net,
			bl.annual_amount,
			bl.monthly_amount,
			bl.recurrence_rule
		FROM `tabMPIT Budget Line` bl
		WHERE {where}
		""",
		params,
		as_dict=True,
	)

	plan = {m: 0.0 for m in range(1, 13)}
	for line in lines:
		start = line.period_start_date or year_start
		end = line.period_end_date or year_end
		overlap_months = _months_in_year_overlap(start, end, year_start, year_end)
		if not overlap_months:
			continue

		total = (
			line.annual_net
			or line.amount_net
			or line.annual_amount
			or (line.monthly_amount or 0) * len(overlap_months)
			or 0
		)
		if total == 0:
			continue

		months_count = len(overlap_months)
		monthly_value = round(total / months_count, 2)
		for idx, month in enumerate(overlap_months):
			value = monthly_value
			if idx == months_count - 1:
				value = round(total - monthly_value * (months_count - 1), 2)
			plan[month] = plan.get(month, 0) + value

	return plan


def _get_actuals_by_month(filters) -> dict[int, float]:
	params = {"year": filters.year}
	conditions = ["year = %(year)s", "status = 'Verified'", "entry_kind in ('Delta','Allowance Spend')"]

	if filters.get("vendor"):
		conditions.append("vendor = %(vendor)s")
		params["vendor"] = filters.vendor
	if filters.get("project"):
		conditions.append("project = %(project)s")
		params["project"] = filters.project
	if filters.get("contract"):
		conditions.append("contract = %(contract)s")
		params["contract"] = filters.contract

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


def _build_summary(planned_cum: float, actual_cum: float, variance_cum: float) -> list[dict]:
	return [
		{
			"label": _("Plan"),
			"value": frappe.utils.fmt_money(planned_cum),
			"indicator": "blue",
		},
		{
			"label": _("Exceptions / Allowance"),
			"value": frappe.utils.fmt_money(actual_cum),
			"indicator": "blue",
		},
		{
			"label": _("Variance (Exceptions - Plan)"),
			"value": frappe.utils.fmt_money(variance_cum),
			"indicator": "green" if (variance_cum or 0) >= 0 else "red",
		},
	]


def _resolve_budget(filters) -> str | None:
	if filters.get("budget"):
		return filters.budget

	year = filters.get("year")
	if not year:
		return None

	active = frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_kind": "Forecast", "is_active_forecast": 1},
		"name",
	)
	if active:
		return active

	return frappe.db.get_value(
		"MPIT Budget",
		{"year": year, "budget_kind": "Baseline"},
		"name",
	)


def _months_in_year_overlap(start, end, year_start, year_end) -> list[int]:
	"""Return sorted list of months (1-12) where period overlaps the year."""
	overlap_start = max(frappe.utils.getdate(start), year_start)
	overlap_end = min(frappe.utils.getdate(end), year_end)
	if overlap_start > overlap_end:
		return []

	months = []
	current = frappe.utils.getdate(overlap_start.replace(day=1))
	while current <= overlap_end:
		if year_start <= current <= year_end:
			months.append(current.month)
		# advance to first day of next month
		if current.month == 12:
			current = current.replace(year=current.year + 1, month=1, day=1)
		else:
			current = current.replace(month=current.month + 1, day=1)
	return months
