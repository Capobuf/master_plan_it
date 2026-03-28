from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import make_autoname, revert_series_if_last
from frappe.utils import getdate

from master_plan_it import mpit_defaults
from master_plan_it.master_plan_it.financial_engine import get_project_financial_summary
from master_plan_it.naming_utils import sync_series_to_max


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
        self._validate_dates()

    def _validate_dates(self) -> None:
        if self.start_date and not self.end_date:
            frappe.throw(_("Set both start and end date, or clear both."))
        if self.end_date and not self.start_date:
            frappe.throw(_("Set both start and end date, or clear both."))
        if self.start_date and self.end_date and getdate(self.end_date) < getdate(self.start_date):
            frappe.throw(_("Project end date cannot be before start date."))


@frappe.whitelist()
def get_project_financial_summary_data(project: str, year: str | None = None) -> dict:
    if not project or project.startswith("new-"):
        return {
            "forecast_total_net": 0,
            "actual_total_net": 0,
            "variance_net": 0,
        }

    if not frappe.db.exists("MPIT Project", project):
        return {
            "forecast_total_net": 0,
            "actual_total_net": 0,
            "variance_net": 0,
        }

    return get_project_financial_summary(project, year=year)
