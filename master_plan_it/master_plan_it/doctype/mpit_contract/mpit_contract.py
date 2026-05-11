from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import make_autoname, revert_series_if_last
from frappe.utils import add_days, flt, getdate

from master_plan_it import annualization, mpit_defaults, tax
from master_plan_it.master_plan_it.financial_engine import get_contract_year_contribution_lines
from master_plan_it.naming_utils import sync_series_to_max

VALID_CONTRACT_STATUSES = {"Active", "Pending Renewal", "Renewed"}


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
        if not self.vendor:
            frappe.throw(_("Vendor is required for contracts."))
        if not self.cost_center:
            frappe.throw(_("Cost Center is required for contracts."))

        for term in self.terms:
            term.validate()

        self._auto_compute_term_end_dates()
        self._validate_terms_no_overlap()
        self._validate_fallback_amount_rules()

        self._compute_header_amounts()
        self._compute_current_term()
        self._compute_annual_summaries()
        self._default_next_renewal_date()
        self._normalize_status()

    def _validate_fallback_amount_rules(self) -> None:
        if self.terms:
            return

        if not self.current_amount:
            frappe.throw(_("When no Contract Terms are set, Current Amount is required."))

        if not self.billing_cycle:
            frappe.throw(_("When no Contract Terms are set, Billing Cycle is required."))

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

    def _compute_header_amounts(self) -> None:
        amount = flt(self.current_amount or 0, 2)
        if amount == 0:
            self.current_amount_net = 0
            self.current_amount_vat = 0
            self.current_amount_gross = 0
            self.current_monthly_net = 0
            return

        default_vat = mpit_defaults.get_default_vat_rate()
        if self.vat_rate is None and default_vat is not None:
            self.vat_rate = default_vat

        final_vat_rate = tax.validate_strict_vat(
            amount,
            self.vat_rate,
            default_vat,
            field_label=_("Current Amount"),
        )

        net, vat, gross = tax.split_net_vat_gross(
            amount,
            final_vat_rate,
            bool(self.current_amount_includes_vat),
        )

        self.current_amount_net = flt(net, 2)
        self.current_amount_vat = flt(vat, 2)
        self.current_amount_gross = flt(gross, 2)
        self.current_monthly_net = self._monthly_from_cycle(self.current_amount_net, self.billing_cycle)

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

        if not self.current_amount_net:
            return 0.0

        period_start = getdate(self.start_date) if self.start_date else year_start
        period_end = getdate(self.end_date) if self.end_date else year_end
        if period_start > period_end:
            return 0.0

        overlap_months = annualization.overlap_months(period_start, period_end, year_start, year_end)
        if overlap_months <= 0:
            return 0.0

        return flt(self.current_monthly_net * overlap_months, 2)

    def _default_next_renewal_date(self) -> None:
        if self.auto_renew and not self.next_renewal_date and self.end_date:
            self.next_renewal_date = self.end_date

    def _normalize_status(self) -> None:
        if not self.status:
            self.status = "Active"
        if self.auto_renew and self.status == "Pending Renewal":
            self.status = "Active"

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
    contract.check_permission("read")

    selected_year, has_year_records = _resolve_target_year_name(year)
    if not has_year_records:
        frappe.throw(
            _("No MPIT Year records exist. Create MPIT Year {0} before generating Actual rows.").format(
                selected_year
            )
        )

    if contract.status not in VALID_CONTRACT_STATUSES:
        message = _("Contract status {0} is not actualizable.").format(contract.status or _("Draft"))
        status_info = _get_actualization_status(contract.name, selected_year, preloaded_lines=[])
        return {
            "status": "not_allowed",
            "message": message,
            "year": selected_year,
            "expense_name": status_info.get("expense_name"),
            "actualization_status": status_info.get("actualization_status"),
            "actualization_status_label": status_info.get("actualization_status_label"),
        }

    lines = get_contract_year_contribution_lines(
        selected_year,
        contract_name=contract.name,
        show_zero_rows=False,
    )
    if not lines:
        message = _("Contract {0} does not overlap year {1}. No Actual rows were created.").format(
            contract.name, selected_year
        )
        return {
            "status": "no_overlap",
            "message": message,
            "year": selected_year,
            "expense_name": _get_existing_contract_year_expense_name(contract.name, selected_year),
            "added_rows": 0,
            "expected_rows": 0,
            "actualization_status": "not_created",
            "actualization_status_label": _("Actual current year: not created"),
        }

    expected_refs = {_build_external_reference(selected_year, contract.name, line) for line in lines}
    existing_refs = _get_existing_external_references(contract.name, selected_year, expected_refs)

    expense_name = _get_existing_contract_year_expense_name(contract.name, selected_year)
    created = False
    if expense_name:
        expense_doc = frappe.get_doc("MPIT Expense", expense_name)
        if expense_doc.project:
            frappe.throw(
                _(
                    "Expense {0} has both Contract and Project set. Clear Project before appending Actual rows."
                ).format(expense_doc.name)
            )
    else:
        expense_doc = frappe.new_doc("MPIT Expense")
        expense_doc.update(
            {
                "expense_kind": "Ordinary",
                "expense_title": _("Actual from contract {0} - {1}").format(contract.name, selected_year),
                "workflow_state": "Open",
                "year": selected_year,
                "cost_center": contract.cost_center,
                "vendor": contract.vendor,
                "contract": contract.name,
                "uses_plafond": 0,
                "is_extra": 0,
            }
        )
        created = True

    added_rows = 0
    for line in lines:
        external_reference = _build_external_reference(selected_year, contract.name, line)
        if external_reference in existing_refs:
            continue

        expense_doc.append(
            "rows",
            {
                "row_state": "Active",
                "row_phase": "Actual",
                "row_description": _build_actual_row_description(contract, line),
                "vendor": line.get("vendor"),
                "external_reference": external_reference,
                "amount": flt(line.get("annual_contribution_net"), 2),
                "amount_includes_vat": 0,
                "start_date": line.get("period_start"),
                "end_date": line.get("period_end"),
                "distribution": "all",
            },
        )
        added_rows += 1

    if added_rows:
        if created:
            expense_doc.insert()
            expense_name = expense_doc.name
        else:
            expense_doc.save()
            expense_name = expense_doc.name

    status_info = _get_actualization_status(contract.name, selected_year, preloaded_lines=lines)
    if added_rows == 0:
        message = _("Actual rows already exist for contract {0} in year {1}.").format(
            contract.name, selected_year
        )
        return {
            "status": "noop",
            "message": message,
            "year": selected_year,
            "expense_name": status_info.get("expense_name"),
            "added_rows": 0,
            "expected_rows": len(expected_refs),
            "actualization_status": status_info.get("actualization_status"),
            "actualization_status_label": status_info.get("actualization_status_label"),
        }

    action = "created" if created else "updated"
    message = (
        _("Created expense {0} with {1} Actual rows from contract {2}.")
        if created
        else _("Updated expense {0}: added {1} Actual rows from contract {2}.")
    ).format(expense_name, added_rows, contract.name)
    return {
        "status": action,
        "message": message,
        "year": selected_year,
        "expense_name": expense_name,
        "added_rows": added_rows,
        "expected_rows": len(expected_refs),
        "actualization_status": status_info.get("actualization_status"),
        "actualization_status_label": status_info.get("actualization_status_label"),
    }


@frappe.whitelist()
def get_current_year_actualization_status(contract_name: str, year: str | None = None) -> dict:
    if not contract_name:
        frappe.throw(_("Contract is required."))

    contract = frappe.get_doc("MPIT Contract", contract_name)
    contract.check_permission("read")
    selected_year, _has_year_records = _resolve_target_year_name(year)

    return _get_actualization_status(contract.name, selected_year)


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


def _build_external_reference(year_name: str, contract_name: str, line: dict) -> str:
    if line.get("source_type") == "Contract Term":
        return (
            f"MPIT_CONTRACT_ACTUAL::{year_name}::{contract_name}"
            f"::TERM::{line.get('source_row') or 'UNKNOWN'}"
        )
    return f"MPIT_CONTRACT_ACTUAL::{year_name}::{contract_name}::HEADER"


def _get_existing_external_references(
    contract_name: str, year_name: str, expected_refs: set[str]
) -> set[str]:
    if not expected_refs:
        return set()

    rows = frappe.db.sql(
        """
        SELECT r.external_reference
        FROM `tabMPIT Expense Row` r
        INNER JOIN `tabMPIT Expense` e ON e.name = r.parent
        WHERE e.contract = %(contract)s
          AND e.year = %(year)s
          AND e.expense_kind = 'Ordinary'
          AND r.external_reference IN %(refs)s
        """,
        {
            "contract": contract_name,
            "year": year_name,
            "refs": tuple(sorted(expected_refs)),
        },
        as_dict=True,
    )
    return {row.external_reference for row in rows if row.external_reference}


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


def _build_actual_row_description(contract, line: dict) -> str:
    source = line.get("source_row") if line.get("source_type") == "Contract Term" else "HEADER"
    description = (contract.description or contract.name or "").strip()
    if description:
        return f"{description} [{source}]"
    return f"{contract.name} [{source}]"


def _get_actualization_status(
    contract_name: str,
    year_name: str,
    preloaded_lines: list[dict] | None = None,
) -> dict:
    lines = preloaded_lines
    if lines is None:
        lines = get_contract_year_contribution_lines(
            year_name,
            contract_name=contract_name,
            show_zero_rows=False,
        )

    expected_refs = {_build_external_reference(year_name, contract_name, line) for line in lines}
    existing_refs = _get_existing_external_references(contract_name, year_name, expected_refs)

    if not expected_refs or not existing_refs:
        status = "not_created"
    elif len(existing_refs) < len(expected_refs):
        status = "partial"
    else:
        status = "complete"

    label_map = {
        "not_created": _("Actual current year: not created"),
        "partial": _("Actual current year: partial"),
        "complete": _("Actual current year: complete"),
    }
    return {
        "year": year_name,
        "expense_name": _get_existing_contract_year_expense_name(contract_name, year_name),
        "actualization_status": status,
        "actualization_status_label": label_map[status],
        "expected_rows": len(expected_refs),
        "existing_rows": len(existing_refs),
    }
