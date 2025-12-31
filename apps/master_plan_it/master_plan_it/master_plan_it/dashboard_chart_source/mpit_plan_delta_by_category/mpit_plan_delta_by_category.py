from __future__ import annotations

import json

import frappe
from frappe import _
from frappe.utils import flt, getdate

# This chart source is called via Frappe's whitelisted API.


def _get_budget_name(year: str, kind: str) -> str | None:
	filters = {"year": year, "budget_kind": kind}
	if kind == "Forecast":
		filters["is_active_forecast"] = 1
	return frappe.db.get_value("MPIT Budget", filters, "name")


def _get_category_totals(budget: str | None, cost_center: str | None) -> dict[str, float]:
	if not budget:
		return {}

	params = {"budget": budget}
	conditions = ["parent = %(budget)s", "is_active = 1", "ifnull(category, '') != ''"]

	if cost_center:
		conditions.append("cost_center = %(cost_center)s")
		params["cost_center"] = cost_center

	rows = frappe.db.sql(
		f"""
		SELECT category, SUM(COALESCE(annual_net, amount_net, annual_amount, 0)) AS total
		FROM `tabMPIT Budget Line`
		WHERE {' AND '.join(conditions)}
		GROUP BY category
		""",
		params,
		as_dict=True,
	)
	return {row.category: flt(row.total or 0, 2) for row in rows}


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
		# default to current year if not provided to avoid AttributeError
		year = str(getdate().year)

	cost_center = filters.get("cost_center")
	try:
		top_n = int(filters.get("top_n") or 10)
	except Exception:
		top_n = 10

	baseline = _get_budget_name(year, "Baseline")
	forecast = _get_budget_name(year, "Forecast") or baseline

	baseline_totals = _get_category_totals(baseline, cost_center)
	forecast_totals = _get_category_totals(forecast, cost_center)

	rows = []
	for cat in set(baseline_totals) | set(forecast_totals):
		delta = forecast_totals.get(cat, 0) - baseline_totals.get(cat, 0)
		rows.append((cat, flt(delta, 2)))

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
