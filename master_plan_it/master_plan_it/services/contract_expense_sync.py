from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.utils import add_days, flt, getdate

from master_plan_it.master_plan_it.financial_engine import allocate_contract_amount_to_year

SYNC_PREFIX = "MPIT_CONTRACT_ACTUAL::"


def sync_contract_expenses(contract_name: str) -> dict:
    """Synchronize generated contract expenses across existing MPIT Year records."""
    if not contract_name:
        frappe.throw(_("Contract is required."))

    contract = frappe.get_doc("MPIT Contract", contract_name)
    contract_terms = _get_sorted_terms(contract)
    if not contract_terms:
        return {
            "contract": contract.name,
            "years_processed": 0,
            "expenses_created": 0,
            "expenses_updated": 0,
            "rows_added": 0,
            "rows_updated": 0,
            "rows_cancelled": 0,
            "status": "no_terms",
        }

    years = frappe.get_all(
        "MPIT Year",
        fields=["name", "start_date", "end_date"],
        order_by="start_date asc",
        limit=None,
    )
    if not years:
        return {
            "contract": contract.name,
            "years_processed": 0,
            "expenses_created": 0,
            "expenses_updated": 0,
            "rows_added": 0,
            "rows_updated": 0,
            "rows_cancelled": 0,
            "status": "no_years",
        }

    outcome = {
        "contract": contract.name,
        "years_processed": 0,
        "expenses_created": 0,
        "expenses_updated": 0,
        "rows_added": 0,
        "rows_updated": 0,
        "rows_cancelled": 0,
        "status": "ok",
    }

    for year_doc in years:
        year_name = str(year_doc.name)
        year_start = getdate(year_doc.start_date)
        year_end = getdate(year_doc.end_date)
        expected_rows = _build_expected_rows_for_year(contract, contract_terms, year_name, year_start, year_end)
        year_outcome = _sync_contract_year_expense(contract, year_name, expected_rows)

        outcome["years_processed"] += 1
        outcome["expenses_created"] += year_outcome["expenses_created"]
        outcome["expenses_updated"] += year_outcome["expenses_updated"]
        outcome["rows_added"] += year_outcome["rows_added"]
        outcome["rows_updated"] += year_outcome["rows_updated"]
        outcome["rows_cancelled"] += year_outcome["rows_cancelled"]

    return outcome


def _get_sorted_terms(contract) -> list:
    return sorted([term for term in contract.terms if term.from_date], key=lambda term: getdate(term.from_date))


def _build_expected_rows_for_year(
    contract,
    terms: list,
    year_name: str,
    year_start: datetime.date,
    year_end: datetime.date,
) -> list[dict]:
    expected = []
    for idx, term in enumerate(terms):
        term_start = getdate(term.from_date)
        term_end = _resolve_term_end(terms, idx, year_end)
        period_start = max(term_start, year_start)
        period_end = min(term_end, year_end)
        if period_start > period_end:
            continue

        term_amount_net = flt(term.amount_net if term.amount_net is not None else term.amount, 2)
        annual_contribution = allocate_contract_amount_to_year(
            term_amount_net,
            term.billing_cycle,
            term_start,
            term_end,
            year_start,
            year_end,
        )
        annual_contribution = flt(annual_contribution, 2)
        if annual_contribution == 0:
            continue

        expected.append(
            {
                "external_reference": _build_external_reference(year_name, contract.name, term.name),
                "row_description": _build_row_description(contract, term.name),
                "vendor": contract.vendor,
                "amount": annual_contribution,
                "amount_includes_vat": 0,
                "vat_rate": 0,
                "start_date": period_start,
                "end_date": period_end,
                "distribution": "all",
                "row_phase": "Actual",
                "row_state": "Active",
            }
        )
    return expected


def _resolve_term_end(terms: list, idx: int, fallback_end: datetime.date) -> datetime.date:
    term = terms[idx]
    if term.to_date:
        return getdate(term.to_date)
    if idx + 1 < len(terms):
        return add_days(getdate(terms[idx + 1].from_date), -1)
    return fallback_end


def _sync_contract_year_expense(contract, year_name: str, expected_rows: list[dict]) -> dict:
    expenses = frappe.get_all(
        "MPIT Expense",
        filters={
            "contract": contract.name,
            "year": year_name,
            "expense_kind": "Ordinary",
        },
        fields=["name"],
        order_by="creation asc",
        limit=None,
    )
    if len(expenses) > 1:
        frappe.throw(
            _(
                "Contract {0} has multiple generated expenses for MPIT Year {1}. Keep only one expense before syncing."
            ).format(contract.name, year_name)
        )

    if not expenses and not expected_rows:
        return {
            "expenses_created": 0,
            "expenses_updated": 0,
            "rows_added": 0,
            "rows_updated": 0,
            "rows_cancelled": 0,
        }

    created = False
    if expenses:
        expense_doc = frappe.get_doc("MPIT Expense", expenses[0].name)
        if expense_doc.project:
            frappe.throw(
                _(
                    "Expense {0} has both Contract and Project set. Clear Project before automatic contract sync."
                ).format(expense_doc.name)
            )
    else:
        expense_doc = frappe.new_doc("MPIT Expense")
        expense_doc.update(
            {
                "expense_kind": "Ordinary",
                "expense_title": _("Actual from contract {0} - {1}").format(contract.name, year_name),
                "year": year_name,
                "cost_center": contract.cost_center,
                "contract": contract.name,
                "uses_plafond": 0,
                "is_extra": 0,
                "rows": [],
            }
        )
        created = True

    prefix = f"{SYNC_PREFIX}{year_name}::{contract.name}::TERM::"
    expected_by_reference = {row["external_reference"]: row for row in expected_rows}
    existing_generated_by_reference = {
        row.external_reference: row
        for row in expense_doc.rows
        if (row.external_reference or "").startswith(prefix)
    }

    changed = False
    rows_added = 0
    rows_updated = 0
    rows_cancelled = 0

    if expense_doc.cost_center != contract.cost_center:
        expense_doc.cost_center = contract.cost_center
        changed = True
    expected_title = _("Actual from contract {0} - {1}").format(contract.name, year_name)
    if expense_doc.expense_title != expected_title:
        expense_doc.expense_title = expected_title
        changed = True

    for external_reference, expected in expected_by_reference.items():
        existing_row = existing_generated_by_reference.get(external_reference)
        if not existing_row:
            expense_doc.append("rows", expected)
            rows_added += 1
            changed = True
            continue

        row_changed = _sync_existing_generated_row(existing_row, expected)
        if row_changed:
            rows_updated += 1
            changed = True

    for external_reference, row in existing_generated_by_reference.items():
        if external_reference in expected_by_reference:
            continue
        if row.row_state != "Cancelled":
            row.row_state = "Cancelled"
            rows_cancelled += 1
            changed = True

    if created:
        expense_doc.insert()
    elif changed:
        expense_doc.save()

    return {
        "expenses_created": 1 if created else 0,
        "expenses_updated": 0 if created else int(changed),
        "rows_added": rows_added,
        "rows_updated": rows_updated,
        "rows_cancelled": rows_cancelled,
    }


def _sync_existing_generated_row(row, expected: dict) -> bool:
    changed = False
    for fieldname in (
        "row_state",
        "row_phase",
        "row_description",
        "vendor",
        "amount",
        "amount_includes_vat",
        "vat_rate",
        "start_date",
        "end_date",
        "distribution",
    ):
        current_value = row.get(fieldname)
        expected_value = expected.get(fieldname)
        if current_value != expected_value:
            row.set(fieldname, expected_value)
            changed = True

    if row.spend_date:
        row.spend_date = None
        changed = True
    return changed


def _build_external_reference(year_name: str, contract_name: str, term_name: str) -> str:
    return f"{SYNC_PREFIX}{year_name}::{contract_name}::TERM::{term_name}"


def _build_row_description(contract, term_name: str) -> str:
    return _("{0} [{1}]").format((contract.description or contract.name), term_name)
