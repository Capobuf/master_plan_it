from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import make_autoname, revert_series_if_last
from frappe.utils import getdate

from master_plan_it import mpit_defaults
from master_plan_it.master_plan_it.financial_engine import get_project_financial_summary
from master_plan_it.naming_utils import sync_series_to_max

VALID_PROJECT_STATUSES = {"Open", "On Hold", "Completed", "Cancelled"}


class MPITProject(Document):
    def autoname(self):
        prefix, digits = mpit_defaults.get_project_series()
        series_key = f"{prefix}.{'#' * digits}"
        sync_series_to_max(self.doctype, prefix, digits)
        self.name = make_autoname(series_key, doc=self)

    def on_trash(self):
        prefix, digits = mpit_defaults.get_project_series()
        series_key = f"{prefix}.{'#' * digits}"
        revert_series_if_last(series_key, self.name, doc=self)

    def validate(self):
        if not self.cost_center and not frappe.in_test:
            frappe.throw(_("Cost Center is required on Project."))
        self._normalize_status()
        self._validate_dates()

    def _normalize_status(self) -> None:
        if not self.workflow_state:
            self.workflow_state = "Open"
        if self.workflow_state not in VALID_PROJECT_STATUSES:
            frappe.throw(
                _("Project Status must be one of: Open, On Hold, Completed, Cancelled.")
            )

    def _validate_dates(self) -> None:
        if self.start_date and not self.end_date:
            frappe.throw(_("Set both start and end date, or clear both."))
        if self.end_date and not self.start_date:
            frappe.throw(_("Set both start and end date, or clear both."))
        if self.start_date and self.end_date and getdate(self.end_date) < getdate(self.start_date):
            frappe.throw(_("Project end date cannot be before start date."))


@frappe.whitelist()
def get_project_financial_summary_data(project: str, year: str | None = None) -> dict:
    year_name = _resolve_year_name(year)

    if not project or project.startswith("new-"):
        return {
            "year": year_name,
            "forecast_total_net": 0,
            "actual_total_net": 0,
            "variance_net": 0,
        }

    if not frappe.db.exists("MPIT Project", project):
        return {
            "year": year_name,
            "forecast_total_net": 0,
            "actual_total_net": 0,
            "variance_net": 0,
        }

    summary = get_project_financial_summary(project, year=year_name)
    summary["year"] = str(summary.get("year") or year_name)
    return summary


def _resolve_year_name(year: str | None) -> str:
    if year:
        return str(year)

    today = datetime.date.today()
    current = frappe.db.get_value(
        "MPIT Year",
        {"start_date": ["<=", today], "end_date": [">=", today]},
        "name",
    )
    if current:
        return str(current)

    fallback = frappe.db.get_value("MPIT Year", {}, "name", order_by="year desc")
    if fallback:
        return str(fallback)

    return str(today.year)
