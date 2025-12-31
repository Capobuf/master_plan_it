"""Install hooks for Master Plan IT.

These hooks are intentionally minimal and idempotent. They ensure the app
has its single settings record and baseline years without requiring
external scripts.
"""

from __future__ import annotations

import datetime

import frappe


def _determine_currency() -> str:
	"""Pick a deterministic currency to satisfy the settings singleton."""
	candidates = [
		frappe.db.get_default("currency"),
		frappe.db.get_default("default_currency"),
	]

	for doctype, field in [
		("System Settings", "currency"),
		("System Settings", "default_currency"),
		("Global Defaults", "default_currency"),
	]:
		try:
			candidates.append(frappe.db.get_single_value(doctype, field))
		except Exception:
			candidates.append(None)

	currency = next((c for c in candidates if c), None)
	if not currency:
		currency_list = frappe.db.get_all("Currency", pluck="name", limit=1)
		currency = currency_list[0] if currency_list else None

	if not currency:
		frappe.throw("Currency is required. Please create at least one Currency and set MPIT Settings.")

	return currency


def _ensure_settings() -> None:
	"""Create the singleton MPIT Settings if missing."""
	settings = frappe.get_single("MPIT Settings")
	if not settings.currency:
		settings.currency = _determine_currency()
	# Ensure naming defaults are set (idempotent)
	if not settings.budget_prefix_default:
		settings.budget_prefix_default = "BUD-"
	if not settings.budget_digits_default:
		settings.budget_digits_default = 2
	if not settings.project_prefix_default:
		settings.project_prefix_default = "PRJ-"
	if not settings.project_digits_default:
		settings.project_digits_default = 2
	if not settings.actual_prefix_default:
		settings.actual_prefix_default = "AE-"
	if not settings.actual_digits_default:
		settings.actual_digits_default = 2
	settings.save(ignore_permissions=True)


def _ensure_year(year: int) -> None:
	"""Create a deterministic MPIT Year document if missing."""
	name = str(year)
	if frappe.db.exists("MPIT Year", name) or frappe.db.exists("MPIT Year", {"year": year}):
		return
	doc = frappe.get_doc({
		"doctype": "MPIT Year",
		"year": year,
		"start_date": f"{year}-01-01",
		"end_date": f"{year}-12-31",
	})
	doc.name = name
	doc.insert(ignore_permissions=True)


def _bootstrap_basics() -> None:
	"""Bootstrap minimal records required by existing code."""
	_ensure_settings()

	today = datetime.date.today()
	_ensure_year(today.year)
	_ensure_year(today.year + 1)
	_reload_standard_assets()


def _reload_standard_assets() -> None:
	"""Ensure dashboards/workspaces/chart sources are synced on new sites."""
	# Chart sources used by dashboards
	frappe.reload_doc("master_plan_it", "dashboard_chart_source", "mpit_plan_delta_by_category", force=1)
	# Dashboards and workspace
	frappe.reload_doc("master_plan_it", "dashboard", "master_plan_it_overview", force=1)
	frappe.reload_doc("master_plan_it", "workspace", "master_plan_it", force=1)


def after_install() -> None:
	_bootstrap_basics()


def after_sync() -> None:
	_bootstrap_basics()
