"""Install hooks for Master Plan IT.

These hooks are intentionally minimal and idempotent. They ensure the app
has its single settings record and baseline years without requiring
external scripts.
"""

from __future__ import annotations

import datetime

import frappe


def _ensure_settings() -> None:
	"""Create the singleton MPIT Settings if missing."""
	if frappe.db.exists("MPIT Settings", "MPIT Settings"):
		return
	doc = frappe.get_doc({"doctype": "MPIT Settings"})
	doc.insert(ignore_permissions=True)


def _ensure_year(year: int) -> None:
	"""Create a deterministic MPIT Year document if missing."""
	name = str(year)
	if frappe.db.exists("MPIT Year", name) or frappe.db.exists("MPIT Year", {"year": year}):
		return
	doc = frappe.get_doc({"doctype": "MPIT Year", "year": year})
	doc.name = name
	doc.insert(ignore_permissions=True)


def _bootstrap_basics() -> None:
	"""Bootstrap minimal records required by existing code."""
	_ensure_settings()

	today = datetime.date.today()
	_ensure_year(today.year)
	_ensure_year(today.year + 1)


def after_install() -> None:
	_bootstrap_basics()


def after_sync() -> None:
	_bootstrap_basics()
