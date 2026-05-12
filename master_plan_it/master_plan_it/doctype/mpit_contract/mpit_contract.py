from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import make_autoname, revert_series_if_last
from frappe.utils import add_days, flt, getdate

from master_plan_it import annualization, mpit_defaults
from master_plan_it.master_plan_it.services.contract_expense_sync import sync_contract_expenses
from master_plan_it.naming_utils import sync_series_to_max


class MPITContract(Document):
    def autoname(self):
        prefix, digits = mpit_defaults.get_contract_series()
        series = f"{prefix}.{'#' * digits}"
        sync_series_to_max(self.doctype, prefix, digits)
        self.name = make_autoname(series)

        if not self.description:
            self.description = self.name

    def on_trash(self):
        prefix, digits = mpit_defaults.get_contract_series()
        series_key = f"{prefix}.{'#' * digits}"
        revert_series_if_last(series_key, self.name, doc=self)

    def validate(self):
        self._validate_head()
        self._validate_terms_present()
        self._ensure_term_row_names()

        for term in self.terms:
            term.validate()

        self._auto_compute_term_end_dates()
        self._validate_terms_no_overlap()
        self._sync_auto_renew_successor()
        self._auto_compute_term_end_dates()
        self._validate_terms_no_overlap()

        self.status = self._calculate_status()
        self._compute_current_term()
        self._compute_annual_summaries()
        self._default_next_renewal_date()

    def on_update(self):
        if getattr(self.flags, "skip_contract_expense_sync", False):
            return
        # Contract rows are generators, not budget rows. Generated expenses are
        # the official budget source and must stay synchronized after each save.
        sync_contract_expenses(self.name)

    def _validate_head(self) -> None:
        if not self.vendor:
            frappe.throw(_("Vendor is required for contracts."))
        if not self.cost_center:
            frappe.throw(_("Cost Center is required for contracts."))

    def _validate_terms_present(self) -> None:
        terms = [term for term in self.terms if term.from_date]
        if not terms:
            frappe.throw(_("At least one Contract Term is required."))

    def _ensure_term_row_names(self) -> None:
        # Child row names are required to create stable source/successor links
        # during the first insert, before Frappe auto-assigns child names.
        for term in self.terms:
            if term.from_date and not term.name:
                term.name = frappe.generate_hash(length=10)

    def _auto_compute_term_end_dates(self) -> None:
        terms = [t for t in self.terms if t.from_date]
        if not terms:
            return

        terms_sorted = sorted(terms, key=lambda t: getdate(t.from_date))

        for idx, term in enumerate(terms_sorted):
            is_last = idx + 1 == len(terms_sorted)
            if not is_last and not term.to_date:
                term.to_date = add_days(getdate(terms_sorted[idx + 1].from_date), -1)

    def _validate_terms_no_overlap(self) -> None:
        terms = [t for t in self.terms if t.from_date]
        if len(terms) < 2:
            return

        terms_sorted = sorted(terms, key=lambda t: getdate(t.from_date))
        for idx, term in enumerate(terms_sorted[:-1]):
            if not term.to_date:
                continue
            next_term = terms_sorted[idx + 1]
            if getdate(term.to_date) >= getdate(next_term.from_date):
                frappe.throw(
                    _("Term {0} overlaps with the next term. Review term dates.").format(idx + 1)
                )

    def _sync_auto_renew_successor(self) -> None:
        if not self.auto_renew:
            return

        terms = [t for t in self.terms if t.from_date]
        if not terms:
            return

        manual_terms = [term for term in terms if not bool(term.is_auto_renewed)]
        if not manual_terms:
            return

        source_term = max(manual_terms, key=lambda term: (getdate(term.from_date), term.idx or 0))
        source_name = source_term.name
        if not source_name:
            return

        source_successors = self._get_auto_renew_successors(source_name)

        if not source_term.to_date:
            self._remove_auto_renew_successors(source_successors)
            return

        source_start = getdate(source_term.from_date)
        source_end = getdate(source_term.to_date)
        next_from = add_days(source_end, 1)
        next_to = add_days(next_from, (source_end - source_start).days)

        if not self._mpit_years_exist_for_range(next_from, next_to):
            self._remove_auto_renew_successors(source_successors)
            return

        successors_by_name = {term.name for term in source_successors if term.name}
        if self._range_overlaps_other_terms(next_from, next_to, ignore_names={source_name, *successors_by_name}):
            self._remove_auto_renew_successors(source_successors)
            return

        successor = self._pick_canonical_successor(source_successors)
        if successor is None:
            successor = self.append("terms", {})
            successor.name = frappe.generate_hash(length=10)

        # Auto-generated terms must never recursively renew themselves.
        successor.is_auto_renewed = 1
        successor.renewed_from_term = source_name
        successor.from_date = next_from
        successor.to_date = next_to
        successor.amount = source_term.amount
        successor.amount_includes_vat = source_term.amount_includes_vat
        successor.vat_rate = source_term.vat_rate
        successor.billing_cycle = source_term.billing_cycle
        successor.notes = source_term.notes
        successor.attachment = source_term.attachment

        self._remove_duplicate_auto_renew_successors(source_successors, keep=successor.name)

    def _get_auto_renew_successors(self, source_name: str) -> list:
        return [
            term
            for term in self.terms
            if term.from_date and bool(term.is_auto_renewed) and (term.renewed_from_term or "") == source_name
        ]

    def _pick_canonical_successor(self, successors: list) -> object | None:
        if not successors:
            return None
        return min(
            successors,
            key=lambda term: (getdate(term.from_date), term.idx or 0),
        )

    def _remove_auto_renew_successors(self, successors: list) -> None:
        if not successors:
            return
        successor_object_ids = {id(term) for term in successors}
        self.set("terms", [term for term in self.terms if id(term) not in successor_object_ids])

    def _remove_duplicate_auto_renew_successors(self, successors: list, keep: str | None = None) -> None:
        if not successors:
            return
        keep_name = keep
        if keep_name is None:
            canonical = self._pick_canonical_successor(successors)
            keep_name = canonical.name if canonical else None
        if not keep_name:
            return

        successor_object_ids = {id(term) for term in successors}
        self.set(
            "terms",
            [
                term
                for term in self.terms
                if id(term) not in successor_object_ids or term.name == keep_name
            ],
        )

    def _mpit_years_exist_for_range(self, period_start: datetime.date, period_end: datetime.date) -> bool:
        for year in range(period_start.year, period_end.year + 1):
            if not frappe.db.exists("MPIT Year", str(year)):
                return False
        return True

    def _range_overlaps_other_terms(
        self,
        period_start: datetime.date,
        period_end: datetime.date,
        ignore_names: set[str],
    ) -> bool:
        for term in self.terms:
            if not term.from_date:
                continue
            if term.name in ignore_names:
                continue
            term_start = getdate(term.from_date)
            term_end = getdate(term.to_date) if term.to_date else None
            if term_end is None:
                if term_start <= period_end:
                    return True
                continue
            if term_start <= period_end and term_end >= period_start:
                return True
        return False

    def _compute_current_term(self) -> None:
        self.current_term_amount = None
        self.current_term_billing_cycle = None
        self.current_term_monthly_net = None
        self.current_term_from_date = None

        terms = [t for t in self.terms if t.from_date]
        if not terms:
            self.start_date = self.start_date or None
            self.end_date = self.end_date or None
            return

        today = datetime.date.today()
        terms_sorted = sorted(terms, key=lambda t: getdate(t.from_date))

        self.start_date = terms_sorted[0].from_date
        self.end_date = terms_sorted[-1].to_date or None

        for idx, term in enumerate(terms_sorted):
            term_start = getdate(term.from_date)
            term_end = resolve_term_end(terms_sorted, idx, fallback_end=None)
            in_range = (term_end and term_start <= today <= term_end) or (not term_end and today >= term_start)

            if in_range:
                self.current_term_amount = term.amount
                self.current_term_billing_cycle = term.billing_cycle
                self.current_term_monthly_net = term.monthly_amount_net
                self.current_term_from_date = term.from_date
                self.start_date = term.from_date
                self.end_date = term.to_date or None
                break

    def _compute_annual_summaries(self) -> None:
        today = datetime.date.today()
        self.current_year_label = str(today.year)
        self.next_year_label = str(today.year + 1)
        self.annual_amount_current_year = self._calculate_annual_for_year(today.year)
        self.annual_amount_next_year = self._calculate_annual_for_year(today.year + 1)

    def _calculate_annual_for_year(self, year: int) -> float:
        year_start, year_end = annualization.get_year_bounds(year)

        terms = [t for t in self.terms if t.from_date]
        if terms:
            total = 0.0
            terms_sorted = sorted(terms, key=lambda t: getdate(t.from_date))

            for idx, term in enumerate(terms_sorted):
                term_start = getdate(term.from_date)
                term_end = resolve_term_end(terms_sorted, idx, fallback_end=year_end)

                period_start = max(term_start, year_start)
                period_end = min(term_end, year_end)
                if period_start > period_end:
                    continue

                overlap_months = annualization.overlap_months(period_start, period_end, year_start, year_end)
                if overlap_months <= 0:
                    continue

                monthly_net = self._monthly_from_cycle(flt(term.amount_net or term.amount, 2), term.billing_cycle)
                total += flt(monthly_net * overlap_months, 2)

            return flt(total, 2)

        return 0.0

    def _default_next_renewal_date(self) -> None:
        if self.auto_renew and not self.next_renewal_date and self.end_date:
            self.next_renewal_date = self.end_date

    def _calculate_status(self, today: datetime.date | None = None) -> str:
        terms = [t for t in self.terms if t.from_date]
        if not terms:
            return "Concluded"

        reference_date = today or datetime.date.today()
        max_end = None
        for term in terms:
            if not term.to_date:
                return "Active"
            term_end = getdate(term.to_date)
            if term_end >= reference_date:
                return "Active"
            max_end = term_end if max_end is None else max(max_end, term_end)

        if max_end and max_end < reference_date:
            return "Concluded"
        return "Concluded"

    @staticmethod
    def _monthly_from_cycle(amount_net: float, billing_cycle: str | None) -> float:
        cycle = (billing_cycle or "Monthly").strip()
        if cycle == "Quarterly":
            return flt(amount_net * 4 / 12, 2)
        if cycle == "Annual":
            return flt(amount_net / 12, 2)
        return flt(amount_net, 2)


def resolve_term_end(terms_sorted: list, idx: int, fallback_end=None):
    term = terms_sorted[idx]
    if term.to_date:
        return getdate(term.to_date)
    if idx + 1 < len(terms_sorted):
        return add_days(getdate(terms_sorted[idx + 1].from_date), -1)
    return fallback_end


@frappe.whitelist()
def create_actual_from_contract(contract_name: str, year: str | None = None) -> dict:
    if not contract_name:
        frappe.throw(_("Contract is required."))

    contract = frappe.get_doc("MPIT Contract", contract_name)
    contract.check_permission("write")
    sync_result = sync_contract_expenses(contract.name)
    selected_year, _has_year_records = _resolve_target_year_name(year)
    status_info = _get_sync_status(contract.name, selected_year)
    return {
        "status": "synchronized",
        "message": _("Contract expenses were synchronized automatically."),
        "year": selected_year,
        "expense_name": status_info.get("expense_name"),
        "actualization_status": status_info.get("actualization_status"),
        "actualization_status_label": status_info.get("actualization_status_label"),
        "rows_added": sync_result.get("rows_added", 0),
        "rows_updated": sync_result.get("rows_updated", 0),
        "rows_cancelled": sync_result.get("rows_cancelled", 0),
    }


@frappe.whitelist()
def get_current_year_actualization_status(contract_name: str, year: str | None = None) -> dict:
    if not contract_name:
        frappe.throw(_("Contract is required."))

    contract = frappe.get_doc("MPIT Contract", contract_name)
    contract.check_permission("read")
    selected_year, _has_year_records = _resolve_target_year_name(year)

    return _get_sync_status(contract.name, selected_year)


def _resolve_target_year_name(year: str | None) -> tuple[str, bool]:
    today = datetime.date.today()
    has_year_records = bool(frappe.db.count("MPIT Year"))
    selected_year = str(year).strip() if year else ""
    if not selected_year:
        current_year = frappe.db.get_value(
            "MPIT Year",
            {"start_date": ["<=", today], "end_date": [">=", today]},
            "name",
        )
        if current_year:
            selected_year = str(current_year)
        elif has_year_records:
            frappe.throw(_("No active MPIT Year covers today ({0}).").format(today))
        else:
            selected_year = str(today.year)

    if has_year_records and not frappe.db.exists("MPIT Year", selected_year):
        frappe.throw(_("MPIT Year {0} does not exist.").format(selected_year))

    return selected_year, has_year_records


def _get_existing_contract_year_expense_name(contract_name: str, year_name: str) -> str | None:
    expenses = frappe.get_all(
        "MPIT Expense",
        filters={
            "contract": contract_name,
            "year": year_name,
            "expense_kind": "Ordinary",
            "workflow_state": ["!=", "Cancelled"],
        },
        fields=["name"],
        order_by="creation asc",
        limit=1,
    )
    if expenses:
        return expenses[0].name
    return None


def _get_sync_status(contract_name: str, year_name: str) -> dict:
    expense_name = _get_existing_contract_year_expense_name(contract_name, year_name)
    if not expense_name:
        return {
            "year": year_name,
            "expense_name": None,
            "actualization_status": "not_created",
            "actualization_status_label": _("Actual current year: not created"),
            "expected_rows": 0,
            "existing_rows": 0,
        }

    prefix = f"MPIT_CONTRACT_ACTUAL::{year_name}::{contract_name}::TERM::"
    rows = frappe.get_all(
        "MPIT Expense Row",
        filters={
            "parent": expense_name,
            "parenttype": "MPIT Expense",
            "parentfield": "rows",
            "external_reference": ["like", f"{prefix}%"],
            "row_state": "Active",
        },
        fields=["name"],
        limit=None,
    )
    status = "complete" if rows else "not_created"
    label_map = {
        "not_created": _("Actual current year: not created"),
        "complete": _("Actual current year: complete"),
    }
    return {
        "year": year_name,
        "expense_name": expense_name,
        "actualization_status": status,
        "actualization_status_label": label_map[status],
        "expected_rows": len(rows),
        "existing_rows": len(rows),
    }
