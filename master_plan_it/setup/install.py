"""Install hooks for Master Plan IT.

These hooks are intentionally minimal and idempotent. They ensure the app
has its single settings record and baseline years without requiring
external scripts.
"""

from __future__ import annotations

import datetime
import os

import frappe


def _ensure_settings() -> None:
	"""Create the singleton MPIT Settings if missing and set naming defaults."""
	settings = frappe.get_single("MPIT Settings")
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
	frappe.reload_doc("master_plan_it", "dashboard", "master_plan_it_overview", force=1)
	frappe.reload_doc("master_plan_it", "workspace", "master_plan_it", force=1)
	_reload_doc_folder("dashboard_chart_source")
	_reload_doc_folder("dashboard_chart")


def _reload_doc_folder(folder: str) -> None:
	base_path = frappe.get_app_path("master_plan_it", "master_plan_it", folder)
	if not base_path or not os.path.exists(base_path):
		return

	for entry in os.listdir(base_path):
		if entry.startswith(".") or entry.startswith("_"):
			continue
		entry_path = os.path.join(base_path, entry)
		if not os.path.isdir(entry_path):
			continue
		json_path = os.path.join(entry_path, f"{entry}.json")
		if not os.path.exists(json_path):
			continue
		frappe.reload_doc("master_plan_it", folder, entry, force=1)


def after_install() -> None:
	_bootstrap_basics()


def after_sync() -> None:
	_bootstrap_basics()


def after_migrate() -> None:
	_reload_standard_assets()
