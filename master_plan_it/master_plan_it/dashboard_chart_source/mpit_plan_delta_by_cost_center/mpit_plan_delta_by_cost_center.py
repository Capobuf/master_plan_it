from __future__ import annotations

import json

import frappe
from frappe import _
from frappe.utils import flt, getdate

# Chart source: Plan Delta (Forecast - Baseline) aggregated by Cost Center.
# Inputs: filters (year optional, cost_center optional, top_n optional).
# Output: labels/values for bar chart keyed by cost center totals.


def _get_budget_name(year: str, kind: str) -> str | None:
	filters = {"year": year, "budget_kind": kind}
	if kind == "Forecast":
		filters["is_active_forecast"] = 1
	return frappe.db.get_value("MPIT Budget", filters, "name")


def _get_latest_year() -> str | None:
	row = frappe.db.sql(
		"select year from `tabMPIT Budget` where ifnull(year, '') != '' order by year desc limit 1",
		as_dict=True,
	)
	return row[0].year if row else None


def _get_cost_center_totals(budget: str | None, cost_center: str | None) -> dict[str, float]:
	if not budget:
		return {}

	params = {"budget": budget}
	conditions = ["parent = %(budget)s", "is_active = 1", "ifnull(cost_center, '') != ''"]

	if cost_center:
		conditions.append("cost_center = %(cost_center)s")
		params["cost_center"] = cost_center

	rows = frappe.db.sql(
		f"""
		SELECT cost_center, SUM(COALESCE(annual_net, amount_net, annual_amount, 0)) AS total
		FROM `tabMPIT Budget Line`
		WHERE {' AND '.join(conditions)}
		GROUP BY cost_center
		""",
		params,
		as_dict=True,
	)
	return {row.cost_center: flt(row.total or 0, 2) for row in rows}


def _normalize_filters(raw) -> dict:
	"""Accept dict or JSON string and return a dict."""
	if raw is None:
		return {}
	if isinstance(raw, str):
		try:
			return json.loads(raw) or {}
		except Exception:
			return {}
	if isinstance(raw, dict):
		return raw
	return {}


@frappe.whitelist()
def get(filters=None):
	filters = _normalize_filters(filters)

	year = filters.get("year")
	if not year:
		# Use latest budget year, otherwise fallback to current calendar year
		year = _get_latest_year() or str(getdate().year)

	cost_center = filters.get("cost_center")
	try:
		top_n = int(filters.get("top_n") or 10)
	except Exception:
		top_n = 10

	baseline = _get_budget_name(year, "Baseline")
	forecast = _get_budget_name(year, "Forecast") or baseline

	baseline_totals = _get_cost_center_totals(baseline, cost_center)
	forecast_totals = _get_cost_center_totals(forecast, cost_center)

	rows = []
	for cc in set(baseline_totals) | set(forecast_totals):
		delta = forecast_totals.get(cc, 0) - baseline_totals.get(cc, 0)
		rows.append((cc, flt(delta, 2)))

	# Sort by absolute delta desc and apply top_n if provided
	rows.sort(key=lambda x: abs(x[1]), reverse=True)
	if top_n and top_n > 0:
		rows = rows[:top_n]

	labels = [row[0] for row in rows]
	values = [row[1] for row in rows]

	return {
		"labels": labels,
		"datasets": [
			{
				"name": _("Plan Delta (Forecast - Baseline)"),
				"values": values,
			}
		],
		"type": "Bar",
	}
