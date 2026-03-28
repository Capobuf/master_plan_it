from __future__ import annotations

import datetime

import frappe
from frappe import _, validate_and_sanitize_search_inputs
from frappe.model.document import Document
from frappe.model.naming import make_autoname, revert_series_if_last
from frappe.utils import flt, getdate

from master_plan_it import annualization, mpit_defaults, tax
from master_plan_it.master_plan_it.financial_engine import get_plafond_document_totals
from master_plan_it.naming_utils import sync_series_to_max

ACTIVE_ROW_STATES = {"Active"}


class MPITExpense(Document):
    def autoname(self):
        prefix, digits = mpit_defaults.get_expense_series()
        series = f"{prefix}.{'#' * digits}"
        sync_series_to_max(self.doctype, prefix, digits)
        self.name = make_autoname(series)

    def on_trash(self):
        prefix, digits = mpit_defaults.get_expense_series()
        series_key = f"{prefix}.{'#' * digits}"
        revert_series_if_last(series_key, self.name, doc=self)

    def validate(self):
        self._validate_head()
        self._validate_kind_rules()
        self._validate_rows()
        self._compute_totals()

    def _validate_head(self) -> None:
        if not self.year:
            frappe.throw(_("Year is required."))
        if not self.cost_center:
            frappe.throw(_("Cost Center is required."))

        if not self.workflow_state:
            self.workflow_state = "Open"

    def _validate_kind_rules(self) -> None:
        if self.expense_kind not in {"Ordinary", "Plafond"}:
            frappe.throw(_("Expense Kind must be Ordinary or Plafond."))

        if self.expense_kind == "Plafond":
            if self.project or self.contract:
                frappe.throw(_("Plafond cannot be linked to Project or Contract."))
            if self.uses_plafond or self.is_extra:
                frappe.throw(_("Plafond cannot be flagged as On Plafond or Extra."))
            if self.plafond_expense:
                frappe.throw(_("Plafond cannot reference another Plafond."))
            if self.workflow_state != "Cancelled":
                self._validate_single_non_cancelled_plafond()
            return

        has_project = bool(self.project)
        has_contract = bool(self.contract)
        if has_project and has_contract:
            frappe.throw(_("Ordinary expense allows at most one context: Project or Contract."))

        uses_plafond = bool(self.uses_plafond)
        is_extra = bool(self.is_extra)
        if uses_plafond == is_extra:
            frappe.throw(_("Ordinary expense requires exactly one funding mode: On Plafond or Extra."))

        if uses_plafond:
            if not self.plafond_expense:
                frappe.throw(_("Plafond reference is required when On Plafond is enabled."))
            self._validate_plafond_reference()
        else:
            if self.plafond_expense:
                frappe.throw(_("Plafond reference must be empty when Extra is enabled."))

    def _validate_single_non_cancelled_plafond(self) -> None:
        filters = {
            "expense_kind": "Plafond",
            "workflow_state": ["!=", "Cancelled"],
            "year": self.year,
            "cost_center": self.cost_center,
        }
        existing = frappe.db.get_value("MPIT Expense", filters, "name")
        if existing and existing != self.name:
            frappe.throw(
                _("Only one non-Cancelled Plafond is allowed for this Year and Cost Center. Existing: {0}").format(
                    existing
                )
            )

    def _validate_plafond_reference(self) -> None:
        plafond = frappe.db.get_value(
            "MPIT Expense",
            self.plafond_expense,
            ["name", "expense_kind", "workflow_state", "year", "cost_center"],
            as_dict=True,
        )
        if not plafond:
            frappe.throw(_("Referenced Plafond does not exist."))
        if plafond.expense_kind != "Plafond":
            frappe.throw(_("Referenced document must be a Plafond."))
        if plafond.workflow_state == "Cancelled":
            frappe.throw(_("Referenced Plafond cannot be Cancelled."))
        if str(plafond.year) != str(self.year):
            frappe.throw(_("Referenced Plafond must belong to the same year."))
        if plafond.cost_center != self.cost_center:
            frappe.throw(_("Referenced Plafond must belong to the same cost center."))

    def _validate_rows(self) -> None:
        if not self.rows:
            frappe.throw(_("At least one Expense Row is required."))

        year_start, year_end = annualization.get_year_bounds(self.year)
        for row in self.rows:
            self._validate_row(row, year_start, year_end)
        self._validate_row_replacements()

    def _validate_row_replacements(self) -> None:
        current_row_names = {row.name for row in self.rows if row.name}
        referenced_targets: list[tuple] = []

        for row in self.rows:
            target_row_name = (row.replaces_row_name or "").strip()
            row.replaces_row_name = target_row_name or None
            if not target_row_name:
                continue

            if row.name and target_row_name == row.name:
                frappe.throw(_("Row #{0} cannot replace itself.").format(row.idx))

            referenced_targets.append((row, target_row_name))

        if not referenced_targets:
            return

        unresolved_targets = sorted(
            {target for _row, target in referenced_targets if target not in current_row_names}
        )
        unresolved_rows = {}
        if unresolved_targets:
            unresolved_rows = {
                row.name: row
                for row in frappe.get_all(
                    "MPIT Expense Row",
                    filters={"name": ["in", unresolved_targets]},
                    fields=["name", "parent", "parenttype", "parentfield"],
                )
            }

        for _row, target_row_name in referenced_targets:
            if target_row_name in current_row_names:
                continue

            target_row = unresolved_rows.get(target_row_name)
            if not target_row:
                frappe.throw(_("Referenced row {0} does not exist.").format(target_row_name))

            if (
                target_row.parent != self.name
                or target_row.parenttype != self.doctype
                or target_row.parentfield != "rows"
            ):
                frappe.throw(
                    _("Referenced row {0} must belong to the same Expense document.").format(target_row_name)
                )

    def _validate_row(self, row, year_start: datetime.date, year_end: datetime.date) -> None:
        if not row.row_state:
            row.row_state = "Active"

        if row.row_state not in {"Active", "Replaced", "Cancelled"}:
            frappe.throw(_("Row State must be Active, Replaced, or Cancelled."))

        amount_net = self._sync_row_amounts(row)

        if self.expense_kind == "Plafond":
            if row.start_date or row.end_date:
                frappe.throw(_("Plafond rows cannot use period distribution fields."))
            row.distribution = None
            if row.spend_date and not (year_start <= getdate(row.spend_date) <= year_end):
                frappe.throw(_("Plafond Spend Date must be inside the selected year."))
            return

        if row.row_phase not in {"Estimate", "Quote", "Actual"}:
            frappe.throw(_("Ordinary rows require a valid phase: Estimate, Quote, or Actual."))

        if row.row_phase in {"Estimate", "Quote"} and amount_net < 0:
            frappe.throw(_("Negative amounts are not allowed for Estimate or Quote rows."))

        has_spend_date = bool(row.spend_date)
        has_period_dates = bool(row.start_date or row.end_date)

        if has_spend_date:
            if has_period_dates:
                frappe.throw(_("Use either Spend Date or period fields on the same row, not both."))
            row.start_date = None
            row.end_date = None
            row.distribution = None
        else:
            if not (row.start_date and row.end_date and row.distribution):
                frappe.throw(_("Rows without Spend Date require Start Date, End Date, and Distribution."))

        if row.start_date and row.end_date and getdate(row.start_date) > getdate(row.end_date):
            frappe.throw(_("Start Date cannot be after End Date."))

        for label, value in {
            "Spend Date": row.spend_date,
            "Start Date": row.start_date,
            "End Date": row.end_date,
        }.items():
            if value and not (year_start <= getdate(value) <= year_end):
                frappe.throw(_("{0} must be inside the selected year.").format(label))

    def _sync_row_amounts(self, row) -> float:
        amount = flt(row.amount or 0, 2)
        row.amount = amount

        default_vat = mpit_defaults.get_default_vat_rate()
        if row.vat_rate is None and default_vat is not None:
            row.vat_rate = default_vat

        final_vat_rate = tax.validate_strict_vat(
            amount,
            row.vat_rate,
            default_vat,
            field_label=_("Expense Row Amount"),
        )
        net, vat, gross = tax.split_net_vat_gross(
            amount,
            final_vat_rate,
            bool(row.amount_includes_vat),
        )

        row.amount_net = flt(net, 2)
        row.amount_vat = flt(vat, 2)
        row.amount_gross = flt(gross, 2)
        return row.amount_net

    def _compute_totals(self) -> None:
        estimate_total = 0.0
        quote_total = 0.0
        actual_total = 0.0

        for row in self.rows:
            if row.row_state not in ACTIVE_ROW_STATES:
                continue

            amount = flt(row.amount_net, 2)
            if self.expense_kind == "Ordinary":
                if row.row_phase == "Estimate":
                    estimate_total += amount
                elif row.row_phase == "Quote":
                    quote_total += amount
                elif row.row_phase == "Actual":
                    actual_total += amount

        self.total_estimate_net = flt(estimate_total, 2)
        self.total_quote_net = flt(quote_total, 2)
        self.total_forecast_net = flt(estimate_total + quote_total, 2)
        self.total_actual_net = flt(actual_total if self.expense_kind == "Ordinary" else 0, 2)

        if self.expense_kind == "Ordinary" and self.uses_plafond:
            self.total_actual_on_plafond_net = self.total_actual_net
            self.total_actual_extra_net = 0
        elif self.expense_kind == "Ordinary" and self.is_extra:
            self.total_actual_on_plafond_net = 0
            self.total_actual_extra_net = self.total_actual_net
        else:
            self.total_actual_on_plafond_net = 0
            self.total_actual_extra_net = 0


@frappe.whitelist()
def get_available_plafonds(year: str, cost_center: str) -> list[str]:
    if not year or not cost_center:
        return []
    return frappe.get_all(
        "MPIT Expense",
        filters={
            "expense_kind": "Plafond",
            "workflow_state": ["!=", "Cancelled"],
            "year": year,
            "cost_center": cost_center,
        },
        order_by="modified desc",
        pluck="name",
    )


@frappe.whitelist()
@validate_and_sanitize_search_inputs
def get_expense_row_replacement_options(
    doctype: str,
    txt: str,
    searchfield: str,
    start: int,
    page_len: int,
    filters: dict | None = None,
) -> list[tuple[str, str, str]]:
    del doctype, searchfield

    filters = filters or {}
    parent_expense = (filters.get("parent_expense") or "").strip()
    current_row_name = (filters.get("current_row_name") or "").strip()
    if not parent_expense:
        return []

    if not frappe.db.exists("MPIT Expense", parent_expense):
        return []
    if not frappe.has_permission("MPIT Expense", doc=parent_expense, ptype="read"):
        return []

    db_filters: dict = {
        "parent": parent_expense,
        "parenttype": "MPIT Expense",
        "parentfield": "rows",
    }
    if current_row_name:
        db_filters["name"] = ["!=", current_row_name]

    or_filters = None
    if txt:
        search_text = f"%{txt}%"
        or_filters = [
            ["MPIT Expense Row", "name", "like", search_text],
            ["MPIT Expense Row", "row_description", "like", search_text],
            ["MPIT Expense Row", "row_phase", "like", search_text],
        ]

    rows = frappe.get_all(
        "MPIT Expense Row",
        filters=db_filters,
        or_filters=or_filters,
        fields=["name", "idx", "row_phase", "row_description"],
        order_by="idx asc",
        limit_start=max(int(start or 0), 0),
        limit_page_length=max(int(page_len or 20), 1),
    )

    results = []
    for row in rows:
        label = "#{0} · {1} · {2}".format(
            row.get("idx") or "?",
            row.get("row_phase") or "-",
            row.get("row_description") or row.get("name"),
        )
        results.append((row.get("name"), label, row.get("name")))

    return results


@frappe.whitelist()
def get_plafond_snapshot(expense_name: str) -> dict:
    doc = frappe.get_doc("MPIT Expense", expense_name)
    if doc.expense_kind != "Plafond":
        return {}

    totals = get_plafond_document_totals(doc.name)
    return {
        "plafond_total": flt(totals.get("plafond_total", 0), 2),
        "plafond_consumed": flt(totals.get("plafond_consumed", 0), 2),
        "plafond_remaining": flt(totals.get("plafond_remaining", 0), 2),
        "plafond_over": flt(totals.get("plafond_over", 0), 2),
    }
