from __future__ import annotations

import calendar
import datetime
from collections import defaultdict

import frappe
from frappe import _
from frappe.utils import flt, getdate

from master_plan_it import annualization

ACTIVE_CONTRACT_STATUSES = {"Active"}
ACTIVE_ROW_STATES = {"Active"}

PROJECT_STATE_IDEA = "Idea"
PROJECT_STATE_PROPOSED = "Proposed"
PROJECT_STATE_APPROVED = "Approved"
PROJECT_STATE_DEFERRED = "Deferred"
PROJECT_STATE_REJECTED = "Rejected"


def _get_project_bucket(row: dict) -> str:
    effective_project = row.get("effective_project")
    if not effective_project:
        return "No Project"

    state = (row.get("effective_project_state") or "").strip()
    if state in {
        PROJECT_STATE_IDEA,
        PROJECT_STATE_PROPOSED,
        PROJECT_STATE_APPROVED,
        PROJECT_STATE_DEFERRED,
        PROJECT_STATE_REJECTED,
    }:
        return state
    return "No Project"


def _get_project_bucket_label(bucket: str) -> str:
    if bucket in {
        "No Project",
        PROJECT_STATE_IDEA,
        PROJECT_STATE_PROPOSED,
        PROJECT_STATE_APPROVED,
        PROJECT_STATE_DEFERRED,
        PROJECT_STATE_REJECTED,
    }:
        return _(bucket)
    return _("No Project")


def _is_forecast_bucket_included(bucket: str) -> bool:
    return bucket in {"No Project", PROJECT_STATE_PROPOSED, PROJECT_STATE_APPROVED}


def _is_actual_bucket_included(bucket: str) -> bool:
    return bucket in {"No Project", PROJECT_STATE_APPROVED}


def get_year_bounds(year: str | int) -> tuple[datetime.date, datetime.date]:
    return annualization.get_year_bounds(_resolve_year_int(year))


def allocate_contract_amount_to_year(
    amount_net: float,
    billing_cycle: str | None,
    period_start: datetime.date,
    period_end: datetime.date,
    year_start: datetime.date,
    year_end: datetime.date,
) -> float:
    return annualization.allocate_billing_cycle_amount_to_year(
        amount_net,
        billing_cycle,
        period_start,
        period_end,
        year_start,
        year_end,
    )


def allocate_expense_row_to_months(
    row: dict,
    year_start: datetime.date,
    year_end: datetime.date,
) -> dict[datetime.date, float]:
    amount = flt(row.get("amount_net"), 2)
    if amount == 0:
        return {}

    spend_date = row.get("spend_date")
    if spend_date:
        spend = getdate(spend_date)
        if year_start <= spend <= year_end:
            return {datetime.date(spend.year, spend.month, 1): amount}
        return {}

    start_date = row.get("start_date")
    end_date = row.get("end_date")
    if not start_date or not end_date:
        return {}

    period_start = max(getdate(start_date), year_start)
    period_end = min(getdate(end_date), year_end)
    if period_start > period_end:
        return {}

    months = _month_periods_touched(period_start, period_end)
    if not months:
        return {}

    distribution = (row.get("distribution") or "all").lower()
    if distribution == "start":
        return {months[0]: amount}
    if distribution == "end":
        return {months[-1]: amount}

    per_month = flt(amount / len(months), 6)
    out: dict[datetime.date, float] = {}
    for month_start in months:
        out[month_start] = flt(out.get(month_start, 0) + per_month, 6)
    return out


def get_contract_forecast_totals(
    year: str | int,
    cost_center: str | None = None,
    contract: str | None = None,
    vendor: str | None = None,
) -> dict:
    year_int = _resolve_year_int(year)
    year_start, year_end = annualization.get_year_bounds(year_int)

    filters: dict = {"status": ["in", list(ACTIVE_CONTRACT_STATUSES)]}
    if cost_center:
        filters["cost_center"] = cost_center
    if contract:
        filters["name"] = contract
    if vendor:
        filters["vendor"] = vendor

    contracts = frappe.get_all(
        "MPIT Contract",
        filters=filters,
        fields=[
            "name",
            "cost_center",
        ],
        order_by="name asc",
        limit=None,
    )

    rows = []
    total = 0.0
    for contract_row in contracts:
        forecast = _contract_forecast_for_year(contract_row, year_start, year_end)
        rows.append(
            {
                "contract": contract_row.name,
                "cost_center": contract_row.cost_center,
                "forecast_net": flt(forecast, 2),
            }
        )
        total += flt(forecast, 2)

    return {
        "year": str(year_int),
        "contract_forecast_total": flt(total, 2),
        "contracts": rows,
    }


def get_expense_forecast_totals(
    year: str | int,
    cost_center: str | None = None,
    project: str | None = None,
    contract: str | None = None,
    vendor: str | None = None,
) -> dict:
    rows = _get_active_rows(
        year,
        cost_center=cost_center,
        project=project,
        contract=contract,
        vendor=vendor,
        expense_kind="Ordinary",
        row_phases=("Estimate", "Quote"),
    )

    estimate_total = 0.0
    quote_total = 0.0
    approved_budget = 0.0
    proposals_total = 0.0
    ideas_total = 0.0
    for row in rows:
        amount = flt(row.get("amount_net"), 2)
        bucket = _get_project_bucket(row)
        if row.get("row_phase") not in {"Estimate", "Quote"}:
            continue

        if bucket == PROJECT_STATE_APPROVED:
            approved_budget += amount
        elif bucket == PROJECT_STATE_PROPOSED:
            proposals_total += amount
        elif bucket == PROJECT_STATE_IDEA:
            ideas_total += amount

        if not _is_forecast_bucket_included(bucket):
            continue

        if row.get("row_phase") == "Estimate":
            estimate_total += amount
        elif row.get("row_phase") == "Quote":
            quote_total += amount

    return {
        "estimate_total": flt(estimate_total, 2),
        "quote_total": flt(quote_total, 2),
        "expense_forecast_total": flt(estimate_total + quote_total, 2),
        "approved_budget": flt(approved_budget, 2),
        "proposals_total": flt(proposals_total, 2),
        "ideas_total": flt(ideas_total, 2),
    }


def get_actual_totals(
    year: str | int,
    cost_center: str | None = None,
    project: str | None = None,
    contract: str | None = None,
    vendor: str | None = None,
) -> dict:
    rows = _get_active_rows(
        year,
        cost_center=cost_center,
        project=project,
        contract=contract,
        vendor=vendor,
        expense_kind="Ordinary",
        row_phases=("Actual",),
    )

    actual_total = 0.0
    actual_standard = 0.0
    actual_on_plafond = 0.0
    actual_extra = 0.0

    for row in rows:
        bucket = _get_project_bucket(row)
        if not _is_actual_bucket_included(bucket):
            continue

        amount = flt(row.get("amount_net"), 2)
        actual_total += amount
        if row.get("uses_plafond"):
            actual_on_plafond += amount
        elif row.get("is_extra"):
            actual_extra += amount
        else:
            # A row with neither flag is a planned ordinary expense, not an Extra.
            actual_standard += amount

    return {
        "actual_total": flt(actual_total, 2),
        "actual_standard": flt(actual_standard, 2),
        "actual_on_plafond": flt(actual_on_plafond, 2),
        "actual_extra": flt(actual_extra, 2),
    }


def get_plafond_totals(year: str | int, cost_center: str | None = None) -> dict:
    year_int = _resolve_year_int(year)

    filters: dict = {
        "year": str(year_int),
        "expense_kind": "Plafond",
    }
    if cost_center:
        filters["cost_center"] = ["in", _get_cost_center_scope(cost_center)]

    plafonds = frappe.get_all("MPIT Expense", filters=filters, pluck="name", limit=None)
    plafond_total = 0.0
    plafond_consumed = 0.0
    for plafond_name in plafonds:
        document_totals = get_plafond_document_totals(plafond_name)
        plafond_total += flt(document_totals.get("plafond_total"), 2)
        plafond_consumed += flt(document_totals.get("plafond_consumed"), 2)

    return {
        "plafond_total": flt(plafond_total, 2),
        "plafond_consumed": flt(plafond_consumed, 2),
        "plafond_remaining": flt(plafond_total - plafond_consumed, 2),
        "plafond_over": flt(max(plafond_consumed - plafond_total, 0), 2),
    }


def get_plafond_document_totals(plafond_expense: str) -> dict:
    if not plafond_expense:
        return {
            "plafond_total": 0.0,
            "plafond_consumed": 0.0,
            "plafond_remaining": 0.0,
            "plafond_over": 0.0,
        }

    plafond_doc = frappe.db.get_value(
        "MPIT Expense",
        plafond_expense,
        ["name", "expense_kind"],
        as_dict=True,
    )
    if not plafond_doc or plafond_doc.expense_kind != "Plafond":
        return {
            "plafond_total": 0.0,
            "plafond_consumed": 0.0,
            "plafond_remaining": 0.0,
            "plafond_over": 0.0,
        }

    plafond_total = flt(
        frappe.db.sql(
            """
            SELECT COALESCE(SUM(r.amount_net), 0)
            FROM `tabMPIT Expense Row` r
            WHERE r.parent = %(plafond_expense)s
              AND r.row_state = 'Active'
            """,
            {"plafond_expense": plafond_expense},
        )[0][0],
        2,
    )

    plafond_consumed = flt(
        frappe.db.sql(
            """
            SELECT COALESCE(SUM(r.amount_net), 0)
            FROM `tabMPIT Expense Row` r
            INNER JOIN `tabMPIT Expense` e ON e.name = r.parent
            WHERE e.expense_kind = 'Ordinary'
              AND e.uses_plafond = 1
              AND e.plafond_expense = %(plafond_expense)s
              AND r.row_state = 'Active'
              AND r.row_phase = 'Actual'
            """,
            {"plafond_expense": plafond_expense},
        )[0][0],
        2,
    )

    return {
        "plafond_total": plafond_total,
        "plafond_consumed": plafond_consumed,
        "plafond_remaining": flt(plafond_total - plafond_consumed, 2),
        "plafond_over": flt(max(plafond_consumed - plafond_total, 0), 2),
    }


def get_cost_center_financial_summary(
    year: str | int,
    cost_center: str,
    project: str | None = None,
    contract: str | None = None,
    vendor: str | None = None,
) -> dict:
    expense_forecast = get_expense_forecast_totals(
        year,
        cost_center=cost_center,
        project=project,
        contract=contract,
        vendor=vendor,
    )
    actual_totals = get_actual_totals(
        year,
        cost_center=cost_center,
        project=project,
        contract=contract,
        vendor=vendor,
    )
    plafond_totals = get_plafond_totals(year, cost_center=cost_center)

    # Official budget totals are built from expense rows only. Contract totals are
    # context/generator data and would double-count once contract sync is enabled.
    forecast_total = flt(expense_forecast.get("expense_forecast_total", 0), 2)

    return {
        "cost_center": cost_center,
        "forecast_contracts": 0.0,
        "forecast_estimate": flt(expense_forecast.get("estimate_total", 0), 2),
        "forecast_quote": flt(expense_forecast.get("quote_total", 0), 2),
        "forecast_total": forecast_total,
        "approved_budget": flt(expense_forecast.get("approved_budget", 0), 2),
        "proposals": flt(expense_forecast.get("proposals_total", 0), 2),
        "ideas": flt(expense_forecast.get("ideas_total", 0), 2),
        "actual_standard": flt(actual_totals.get("actual_standard", 0), 2),
        "actual_on_plafond": flt(actual_totals.get("actual_on_plafond", 0), 2),
        "actual_extra": flt(actual_totals.get("actual_extra", 0), 2),
        "actual_total": flt(actual_totals.get("actual_total", 0), 2),
        "plafond": flt(plafond_totals.get("plafond_total", 0), 2),
        "plafond_consumed": flt(plafond_totals.get("plafond_consumed", 0), 2),
        "remaining": flt(plafond_totals.get("plafond_remaining", 0), 2),
        "over": flt(plafond_totals.get("plafond_over", 0), 2),
    }


def get_project_financial_summary(project: str, year: str | int | None = None) -> dict:
    year_int = _resolve_year_int(year)

    forecast = get_expense_forecast_totals(year_int, project=project)
    actual = get_actual_totals(year_int, project=project)

    forecast_total = flt(forecast.get("expense_forecast_total", 0), 2)
    actual_total = flt(actual.get("actual_total", 0), 2)

    return {
        "project": project,
        "year": str(year_int),
        "forecast_total_net": forecast_total,
        "actual_total_net": actual_total,
        "variance_net": flt(forecast_total - actual_total, 2),
        "estimate_total_net": flt(forecast.get("estimate_total", 0), 2),
        "quote_total_net": flt(forecast.get("quote_total", 0), 2),
    }


def get_overview_dataset(
    year: str | int,
    cost_center: str | None = None,
    project: str | None = None,
    contract: str | None = None,
    vendor: str | None = None,
) -> dict:
    year_int = _resolve_year_int(year)

    if cost_center:
        cost_centers = [cost_center]
    else:
        cost_centers = _resolve_cost_centers_for_year(year_int, None)

    rows = []
    for cc in cost_centers:
        rows.append(
            get_cost_center_financial_summary(
                year_int,
                cc,
                project=project,
                contract=contract,
                vendor=vendor,
            )
        )

    summary = {
        "forecast_contracts": flt(sum(row["forecast_contracts"] for row in rows), 2),
        "forecast_estimate": flt(sum(row["forecast_estimate"] for row in rows), 2),
        "forecast_quote": flt(sum(row["forecast_quote"] for row in rows), 2),
        "forecast_total": flt(sum(row["forecast_total"] for row in rows), 2),
        "approved_budget": flt(sum(row.get("approved_budget", 0) for row in rows), 2),
        "proposals": flt(sum(row.get("proposals", 0) for row in rows), 2),
        "ideas": flt(sum(row.get("ideas", 0) for row in rows), 2),
        "actual_standard": flt(sum(row["actual_standard"] for row in rows), 2),
        "actual_on_plafond": flt(sum(row["actual_on_plafond"] for row in rows), 2),
        "actual_extra": flt(sum(row["actual_extra"] for row in rows), 2),
        "actual_total": flt(sum(row["actual_total"] for row in rows), 2),
        "plafond": flt(sum(row["plafond"] for row in rows), 2),
        "plafond_consumed": flt(sum(row["plafond_consumed"] for row in rows), 2),
        "remaining": flt(sum(row["remaining"] for row in rows), 2),
        "over": flt(sum(row["over"] for row in rows), 2),
    }

    return {"year": str(year_int), "rows": rows, "summary": summary}


def get_overview_buildup_dataset(
    year: str | int,
    cost_center: str | None = None,
    section_scope: str | None = None,
    contract: str | None = None,
    project: str | None = None,
    vendor: str | None = None,
    show_zero_rows: bool = False,
) -> dict:
    """
    Returns hierarchical (build-up) rows for the MPIT Overview report.

    Each cost center produces a header row (indent=0) followed by block rows
    (indent=1) that explain which components compose the total.
    """
    year_int = _resolve_year_int(year)
    scope = (section_scope or "All").strip()
    include_expenses = scope in ("All", "Expenses")
    include_plafond = scope in ("All", "Plafond")

    cost_centers = _resolve_cost_centers_for_year(year_int, cost_center)

    rows: list[dict] = []
    grand = _zero_summary()

    for cc in cost_centers:
        fc_contracts = 0.0

        # --- expense forecast blocks ---
        fc_estimate = 0.0
        fc_quote = 0.0
        approved_budget = 0.0
        proposals = 0.0
        ideas = 0.0
        if include_expenses:
            ef = get_expense_forecast_totals(
                year_int,
                cost_center=cc,
                project=project,
                contract=contract,
                vendor=vendor,
            )
            fc_estimate = flt(ef.get("estimate_total", 0), 2)
            fc_quote = flt(ef.get("quote_total", 0), 2)
            approved_budget = flt(ef.get("approved_budget", 0), 2)
            proposals = flt(ef.get("proposals_total", 0), 2)
            ideas = flt(ef.get("ideas_total", 0), 2)

        # --- actual blocks ---
        actual_standard = 0.0
        actual_on_plafond = 0.0
        actual_extra = 0.0
        actual_total = 0.0
        if include_expenses:
            at = get_actual_totals(
                year_int,
                cost_center=cc,
                project=project,
                contract=contract,
                vendor=vendor,
            )
            actual_standard = flt(at.get("actual_standard", 0), 2)
            actual_on_plafond = flt(at.get("actual_on_plafond", 0), 2)
            actual_extra = flt(at.get("actual_extra", 0), 2)
            actual_total = flt(at.get("actual_total", 0), 2)

        # --- plafond block ---
        plafond = 0.0
        plafond_consumed = 0.0
        remaining = 0.0
        over = 0.0
        if include_plafond:
            pt = get_plafond_totals(year_int, cost_center=cc)
            plafond = flt(pt.get("plafond_total", 0), 2)
            plafond_consumed = flt(pt.get("plafond_consumed", 0), 2)
            remaining = flt(pt.get("plafond_remaining", 0), 2)
            over = flt(pt.get("plafond_over", 0), 2)

        fc_total = flt(fc_estimate + fc_quote, 2)

        # Skip empty CC when show_zero_rows is False
        if not show_zero_rows and fc_total == 0 and actual_total == 0 and plafond == 0:
            continue

        # CC header row (indent=0, bold)
        header = _make_summary_row(
            cc,
            fc_contracts, fc_estimate, fc_quote, fc_total,
            approved_budget, proposals, ideas,
            actual_standard,
            actual_on_plafond, actual_extra, actual_total,
            plafond, plafond_consumed, remaining, over,
        )
        header.update({"indent": 0, "bold": 1})
        rows.append(header)

        # --- block rows (indent=1) ---
        if include_expenses:
            if show_zero_rows or fc_estimate:
                rows.append(_block_row(cc, _("Expenses / Estimate"), forecast_estimate=fc_estimate, indent=1))
            if show_zero_rows or fc_quote:
                rows.append(_block_row(cc, _("Expenses / Quote"), forecast_quote=fc_quote, indent=1))
            if show_zero_rows or approved_budget:
                rows.append(_block_row(cc, _("Approved Budget"), approved_budget=approved_budget, indent=1))
            if show_zero_rows or proposals:
                rows.append(_block_row(cc, _("Proposals"), proposals=proposals, indent=1))
            if show_zero_rows or ideas:
                rows.append(_block_row(cc, _("Ideas"), ideas=ideas, indent=1))
            if show_zero_rows or actual_standard:
                rows.append(_block_row(cc, _("Actual / Standard"), actual_standard=actual_standard, indent=1))
            if show_zero_rows or actual_on_plafond:
                rows.append(
                    _block_row(cc, _("Actual / On Plafond"), actual_on_plafond=actual_on_plafond, indent=1)
                )
            if show_zero_rows or actual_extra:
                rows.append(_block_row(cc, _("Actual / Extra"), actual_extra=actual_extra, indent=1))

        if include_plafond and (show_zero_rows or plafond):
            rows.append(
                _block_row(
                    cc,
                    _("Plafond"),
                    plafond=plafond,
                    plafond_consumed=plafond_consumed,
                    remaining=remaining,
                    over=over,
                    indent=1,
                )
            )

        # Accumulate grand summary
        grand["forecast_contracts"] = flt(grand["forecast_contracts"] + fc_contracts, 2)
        grand["forecast_estimate"] = flt(grand["forecast_estimate"] + fc_estimate, 2)
        grand["forecast_quote"] = flt(grand["forecast_quote"] + fc_quote, 2)
        grand["forecast_total"] = flt(grand["forecast_total"] + fc_total, 2)
        grand["approved_budget"] = flt(grand["approved_budget"] + approved_budget, 2)
        grand["proposals"] = flt(grand["proposals"] + proposals, 2)
        grand["ideas"] = flt(grand["ideas"] + ideas, 2)
        grand["actual_standard"] = flt(grand["actual_standard"] + actual_standard, 2)
        grand["actual_on_plafond"] = flt(grand["actual_on_plafond"] + actual_on_plafond, 2)
        grand["actual_extra"] = flt(grand["actual_extra"] + actual_extra, 2)
        grand["actual_total"] = flt(grand["actual_total"] + actual_total, 2)
        grand["plafond"] = flt(grand["plafond"] + plafond, 2)
        grand["plafond_consumed"] = flt(grand["plafond_consumed"] + plafond_consumed, 2)
        grand["remaining"] = flt(grand["remaining"] + remaining, 2)
        grand["over"] = flt(grand["over"] + over, 2)

    return {
        "year": str(year_int),
        "rows": rows,
        "summary": grand,
        "project_filter_active": bool(project),
    }


def get_overview_lines_dataset(
    year: str | int,
    cost_center: str | None = None,
    section_scope: str | None = None,
    expense_phase: str | None = None,
    contract: str | None = None,
    project: str | None = None,
    vendor: str | None = None,
    show_zero_rows: bool = False,
) -> dict:
    """
    Returns individual contribution lines for the MPIT Overview Lines mode.

    Line types:
    - Expense Row    : one row per active MPIT Expense Row (Estimate/Quote/Actual)
    - Plafond        : one row per active MPIT Expense Row in a Plafond document
    """
    year_int = _resolve_year_int(year)
    scope = (section_scope or "All").strip()
    include_expenses = scope in ("All", "Expenses")
    include_plafond = scope in ("All", "Plafond")

    rows: list[dict] = []

    # ------------------------------------------------------------------
    # Expense rows (Estimate / Quote / Actual)
    # ------------------------------------------------------------------
    if include_expenses:
        row_phases: tuple[str, ...] | None = None
        if expense_phase and expense_phase != "All":
            row_phases = (expense_phase,)
        else:
            row_phases = ("Estimate", "Quote", "Actual")

        expense_rows = _get_active_rows(
            year_int,
            cost_center=cost_center,
            project=project,
            contract=contract,
            vendor=vendor,
            expense_kind="Ordinary",
            row_phases=row_phases,
        )

        for row in expense_rows:
            amount = flt(row.get("amount_net"), 2)
            if not show_zero_rows and amount == 0:
                continue

            funding_key = "Standard"
            if row.get("uses_plafond"):
                funding_key = "On Plafond"
            elif row.get("is_extra"):
                funding_key = "Extra"
            project_bucket = _get_project_bucket(row)
            expense_phase = row.get("row_phase")

            rows.append({
                "cost_center": row.get("cost_center"),
                "source_type": _("Expense Row"),
                "source_type_key": "Expense Row",
                "source_document": row.get("expense"),
                "source_row": row.get("row_name"),
                "contract": row.get("contract"),
                "project": row.get("effective_project"),
                "project_bucket": _get_project_bucket_label(project_bucket),
                "project_bucket_key": project_bucket,
                "vendor": row.get("vendor"),
                "expense_phase": _(expense_phase) if expense_phase else None,
                "expense_phase_key": expense_phase,
                "funding": _(funding_key),
                "funding_key": funding_key,
                "period_start": getdate(row.get("start_date")) if row.get("start_date") else None,
                "period_end": getdate(row.get("end_date")) if row.get("end_date") else None,
                "spend_date": row.get("spend_date"),
                "amount_net": amount,
                "annual_contribution_net": amount,
            })

    # ------------------------------------------------------------------
    # Plafond rows
    # ------------------------------------------------------------------
    if include_plafond:
        plafond_filters: dict = {
            "year": str(year_int),
            "expense_kind": "Plafond",
        }
        if cost_center:
            plafond_filters["cost_center"] = ["in", _get_cost_center_scope(cost_center)]

        plafonds = frappe.get_all(
            "MPIT Expense",
            filters=plafond_filters,
            fields=["name", "cost_center", "expense_title"],
            order_by="cost_center asc, name asc",
            limit=None,
        )

        for plafond_doc in plafonds:
            plafond_rows_raw = frappe.db.sql(
                """
                SELECT r.name, r.amount_net, r.spend_date, r.row_description, r.vendor
                FROM `tabMPIT Expense Row` r
                WHERE r.parent = %(plafond)s
                  AND r.row_state = 'Active'
                ORDER BY r.idx
                """,
                {"plafond": plafond_doc.name},
                as_dict=True,
            )
            for row in plafond_rows_raw:
                amount = flt(row.amount_net, 2)
                if not show_zero_rows and amount == 0:
                    continue

                rows.append({
                    "cost_center": plafond_doc.cost_center,
                    "source_type": _("Plafond"),
                    "source_type_key": "Plafond",
                    "source_document": plafond_doc.name,
                    "source_row": row.name,
                    "contract": None,
                    "project": None,
                    "project_bucket": _("No Project"),
                    "project_bucket_key": "No Project",
                    "vendor": row.vendor,
                    "expense_phase": None,
                    "expense_phase_key": None,
                    "funding": _("On Plafond"),
                    "funding_key": "On Plafond",
                    "period_start": None,
                    "period_end": None,
                    "spend_date": row.spend_date,
                    "amount_net": amount,
                    "annual_contribution_net": amount,
                })

    # Sort final rows: cost_center → source_type → source_document
    rows.sort(key=lambda r: (r.get("cost_center") or "", r.get("source_type") or "", r.get("source_document") or ""))

    # Aggregate summary
    summary = {
        "total_lines": len(rows),
        "annual_total": flt(sum(r.get("annual_contribution_net", 0) for r in rows), 2),
    }

    return {
        "year": str(year_int),
        "rows": rows,
        "summary": summary,
        "project_filter_active": bool(project),
    }


def get_contract_year_contribution_lines(
    year: str | int,
    contract_name: str | None = None,
    cost_center: str | None = None,
    vendor: str | None = None,
    show_zero_rows: bool = False,
) -> list[dict]:
    """
    Deterministic contract contribution lines for a specific year.

    Source types:
    - Contract Term: one line per overlapping contract term
    """
    year_int = _resolve_year_int(year)
    year_start, year_end = annualization.get_year_bounds(year_int)

    contract_filters: dict = {"status": ["in", list(ACTIVE_CONTRACT_STATUSES)]}
    if contract_name:
        contract_filters["name"] = contract_name
    if cost_center:
        contract_filters["cost_center"] = cost_center
    if vendor:
        contract_filters["vendor"] = vendor

    contracts = frappe.get_all(
        "MPIT Contract",
        filters=contract_filters,
        fields=[
            "name",
            "vendor",
            "cost_center",
            "status",
        ],
        order_by="cost_center asc, name asc",
        limit=None,
    )

    contribution_lines: list[dict] = []

    for contract_row in contracts:
        terms = _get_contract_terms(contract_row.name)
        if terms:
            for idx, term in enumerate(terms):
                term_end = _resolve_term_end(terms, idx, year_end)
                period_start = max(getdate(term.from_date), year_start)
                period_end = min(term_end, year_end)
                if period_start > period_end:
                    continue

                term_amount_net = flt(term.amount_net if term.amount_net is not None else term.amount, 2)
                annual_contrib = allocate_contract_amount_to_year(
                    term_amount_net,
                    term.billing_cycle,
                    getdate(term.from_date),
                    term_end,
                    year_start,
                    year_end,
                )
                if not show_zero_rows and annual_contrib == 0:
                    continue

                contribution_lines.append(
                    {
                        "source_type": "Contract Term",
                        "source_row": term.name,
                        "contract": contract_row.name,
                        "contract_status": contract_row.status,
                        "cost_center": contract_row.cost_center,
                        "vendor": contract_row.vendor,
                        "period_start": period_start,
                        "period_end": period_end,
                        "amount_net": term_amount_net,
                        "annual_contribution_net": flt(annual_contrib, 2),
                        "billing_cycle": term.billing_cycle,
                        "vat_rate": term.vat_rate,
                        "amount_includes_vat": term.amount_includes_vat,
                    }
                )
            continue

    contribution_lines.sort(
        key=lambda line: (
            line.get("cost_center") or "",
            line.get("contract") or "",
            line.get("source_type") or "",
            line.get("source_row") or "",
        )
    )
    return contribution_lines


# ---------------------------------------------------------------------------
# Private helpers for overview datasets
# ---------------------------------------------------------------------------

def _resolve_cost_centers_for_year(year_int: int, cost_center: str | None) -> list[str]:
    if cost_center:
        return [cost_center]

    from_expenses = frappe.db.sql(
        """
        SELECT DISTINCT e.cost_center
        FROM `tabMPIT Expense` e
        INNER JOIN `tabMPIT Expense Row` r ON r.parent = e.name
        WHERE e.year = %(year)s
          AND r.parenttype = 'MPIT Expense'
          AND r.parentfield = 'rows'
          AND r.row_state = 'Active'
        """,
        {"year": str(year_int)},
        as_dict=True,
    )
    return sorted({row.cost_center for row in from_expenses if row.cost_center})


def _get_cost_center_scope(cost_center: str) -> list[str]:
    cost_center_doc = frappe.db.get_value(
        "MPIT Cost Center",
        cost_center,
        ["is_group", "lft", "rgt"],
        as_dict=True,
    )
    if not cost_center_doc or not cost_center_doc.is_group:
        return [cost_center]

    descendants = frappe.get_all(
        "MPIT Cost Center",
        filters={
            "lft": [">=", cost_center_doc.lft],
            "rgt": ["<=", cost_center_doc.rgt],
        },
        pluck="name",
        limit=None,
    )
    return sorted(descendants) or [cost_center]


def _zero_summary() -> dict:
    return {
        "forecast_contracts": 0.0,
        "forecast_estimate": 0.0,
        "forecast_quote": 0.0,
        "forecast_total": 0.0,
        "approved_budget": 0.0,
        "proposals": 0.0,
        "ideas": 0.0,
        "actual_standard": 0.0,
        "actual_on_plafond": 0.0,
        "actual_extra": 0.0,
        "actual_total": 0.0,
        "plafond": 0.0,
        "plafond_consumed": 0.0,
        "remaining": 0.0,
        "over": 0.0,
    }


def _make_summary_row(
    label: str,
    forecast_contracts: float,
    forecast_estimate: float,
    forecast_quote: float,
    forecast_total: float,
    approved_budget: float,
    proposals: float,
    ideas: float,
    actual_standard: float,
    actual_on_plafond: float,
    actual_extra: float,
    actual_total: float,
    plafond: float,
    plafond_consumed: float,
    remaining: float,
    over: float,
) -> dict:
    return {
        "cost_center": label,
        "forecast_contracts": forecast_contracts,
        "forecast_estimate": forecast_estimate,
        "forecast_quote": forecast_quote,
        "forecast_total": forecast_total,
        "approved_budget": approved_budget,
        "proposals": proposals,
        "ideas": ideas,
        "actual_standard": actual_standard,
        "actual_on_plafond": actual_on_plafond,
        "actual_extra": actual_extra,
        "actual_total": actual_total,
        "plafond": plafond,
        "plafond_consumed": plafond_consumed,
        "remaining": remaining,
        "over": over,
    }


def _block_row(
    cost_center: str,
    label: str,
    *,
    forecast_contracts: float = 0.0,
    forecast_estimate: float = 0.0,
    forecast_quote: float = 0.0,
    approved_budget: float = 0.0,
    proposals: float = 0.0,
    ideas: float = 0.0,
    actual_standard: float = 0.0,
    actual_on_plafond: float = 0.0,
    actual_extra: float = 0.0,
    plafond: float = 0.0,
    plafond_consumed: float = 0.0,
    remaining: float = 0.0,
    over: float = 0.0,
    indent: int = 1,
) -> dict:
    fc_total = flt(forecast_contracts + forecast_estimate + forecast_quote, 2)
    actual_total = flt(actual_standard + actual_on_plafond + actual_extra, 2)
    return {
        "cost_center": label,
        "forecast_contracts": forecast_contracts,
        "forecast_estimate": forecast_estimate,
        "forecast_quote": forecast_quote,
        "forecast_total": fc_total,
        "approved_budget": approved_budget,
        "proposals": proposals,
        "ideas": ideas,
        "actual_standard": actual_standard,
        "actual_on_plafond": actual_on_plafond,
        "actual_extra": actual_extra,
        "actual_total": actual_total,
        "plafond": plafond,
        "plafond_consumed": plafond_consumed,
        "remaining": remaining,
        "over": over,
        "indent": indent,
    }


def get_monthly_forecast_vs_actual(
    year: str | int,
    cost_center: str | None = None,
    project: str | None = None,
    contract: str | None = None,
) -> dict:
    year_int = _resolve_year_int(year)
    year_start, year_end = annualization.get_year_bounds(year_int)

    forecast_by_month = defaultdict(float)
    actual_by_month = defaultdict(float)

    forecast_rows = _get_active_rows(
        year_int,
        cost_center=cost_center,
        project=project,
        contract=contract,
        expense_kind="Ordinary",
        row_phases=("Estimate", "Quote"),
    )
    for row in forecast_rows:
        monthly_map = allocate_expense_row_to_months(row, year_start, year_end)
        for month_start, value in monthly_map.items():
            forecast_by_month[month_start] += flt(value, 6)

    actual_rows = _get_active_rows(
        year_int,
        cost_center=cost_center,
        project=project,
        contract=contract,
        expense_kind="Ordinary",
        row_phases=("Actual",),
    )
    for row in actual_rows:
        monthly_map = allocate_expense_row_to_months(row, year_start, year_end)
        for month_start, value in monthly_map.items():
            actual_by_month[month_start] += flt(value, 6)

    months = []
    forecast_total = 0.0
    actual_total = 0.0

    current = datetime.date(year_start.year, year_start.month, 1)
    limit = datetime.date(year_end.year, year_end.month, 1)
    while current <= limit:
        forecast_value = flt(forecast_by_month.get(current, 0), 2)
        actual_value = flt(actual_by_month.get(current, 0), 2)
        forecast_total += forecast_value
        actual_total += actual_value

        months.append(
            {
                "month_index": current.month,
                "calendar_year": current.year,
                "calendar_month": current.month,
                "month": _format_month_label(current, year_start, year_end),
                "forecast": forecast_value,
                "actual": actual_value,
                "delta": flt(forecast_value - actual_value, 2),
            }
        )

        if current.month == 12:
            current = datetime.date(current.year + 1, 1, 1)
        else:
            current = datetime.date(current.year, current.month + 1, 1)

    return {
        "year": str(year_int),
        "months": months,
        "totals": {
            "forecast_total": flt(forecast_total, 2),
            "actual_total": flt(actual_total, 2),
            "delta_total": flt(forecast_total - actual_total, 2),
        },
    }


def _resolve_year_int(year: str | int | None) -> int:
    if year is None:
        return datetime.date.today().year

    if isinstance(year, int):
        return year

    if str(year).isdigit():
        return int(year)

    year_value = frappe.db.get_value("MPIT Year", str(year), "year")
    if year_value:
        return int(year_value)

    return int(str(year))


def _contract_forecast_for_year(contract_row, year_start: datetime.date, year_end: datetime.date) -> float:
    terms = _get_contract_terms(contract_row.name)

    if terms:
        total = 0.0
        impacted = False
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
            if annual_contribution == 0:
                continue

            impacted = True
            total += annual_contribution

        if not impacted:
            return 0.0
        return flt(total, 2)

    return 0.0


def _get_contract_terms(contract_name: str) -> list:
    terms = frappe.get_all(
        "MPIT Contract Term",
        filters={"parent": contract_name, "parenttype": "MPIT Contract", "parentfield": "terms"},
        fields=[
            "name",
            "from_date",
            "to_date",
            "amount",
            "amount_net",
            "billing_cycle",
            "vat_rate",
            "amount_includes_vat",
        ],
        order_by="from_date asc, idx asc",
        limit=None,
    )
    return [term for term in terms if term.get("from_date")]


def _resolve_term_end(terms: list, idx: int, fallback_end: datetime.date) -> datetime.date:
    term = terms[idx]
    if term.to_date:
        return getdate(term.to_date)
    if idx + 1 < len(terms):
        next_start = getdate(terms[idx + 1].from_date)
        return next_start - datetime.timedelta(days=1)
    return fallback_end


def _get_active_contracts(cost_center: str | None = None, contract: str | None = None) -> list:
    filters: dict = {"status": ["in", list(ACTIVE_CONTRACT_STATUSES)]}
    if cost_center:
        filters["cost_center"] = cost_center
    if contract:
        filters["name"] = contract

    return frappe.get_all(
        "MPIT Contract",
        filters=filters,
        fields=[
            "name",
            "cost_center",
        ],
        order_by="name asc",
        limit=None,
    )


def _contract_monthly_allocation(contract_row, year_start: datetime.date, year_end: datetime.date) -> dict[int, float]:
    monthly_map: dict[int, float] = defaultdict(float)
    terms = _get_contract_terms(contract_row.name)

    if terms:
        for idx, term in enumerate(terms):
            term_start = getdate(term.from_date)
            term_end = _resolve_term_end(terms, idx, year_end)

            period_start = max(term_start, year_start)
            period_end = min(term_end, year_end)
            if period_start > period_end:
                continue

            term_amount_net = flt(term.amount_net if term.amount_net is not None else term.amount, 2)
            monthly_net = annualization.monthly_equivalent_net(term_amount_net, term.billing_cycle, precision=6)

            for month in _months_touched(period_start, period_end):
                monthly_map[month] += flt(monthly_net, 6)
        return monthly_map

    return monthly_map


def _get_active_rows(
    year: str | int,
    cost_center: str | None = None,
    project: str | None = None,
    contract: str | None = None,
    vendor: str | None = None,
    expense_kind: str = "Ordinary",
    row_phases: tuple[str, ...] | None = None,
) -> list[dict]:
    year_int = _resolve_year_int(year)
    cost_centers = _get_cost_center_scope(cost_center) if cost_center else None

    sql = [
        """
        SELECT
            e.name AS expense,
            e.cost_center,
            e.project,
            e.contract,
            COALESCE(e.project, contract_doc.project) AS effective_project,
            COALESCE(project_direct.workflow_state, project_from_contract.workflow_state) AS effective_project_state,
            e.expense_kind,
            e.uses_plafond,
            e.is_extra,
            r.name AS row_name,
            r.row_phase,
            r.row_description,
            r.amount_net,
            r.spend_date,
            r.start_date,
            r.end_date,
            r.distribution,
            r.vendor
        FROM `tabMPIT Expense Row` r
        INNER JOIN `tabMPIT Expense` e ON e.name = r.parent
        LEFT JOIN `tabMPIT Contract` contract_doc ON contract_doc.name = e.contract
        LEFT JOIN `tabMPIT Project` project_direct ON project_direct.name = e.project
        LEFT JOIN `tabMPIT Project` project_from_contract ON project_from_contract.name = contract_doc.project
        WHERE e.year = %(year)s
          AND e.expense_kind = %(expense_kind)s
          AND r.row_state = 'Active'
        """
    ]

    params: dict = {"year": str(year_int), "expense_kind": expense_kind}

    if row_phases:
        sql.append("AND r.row_phase IN %(row_phases)s")
        params["row_phases"] = row_phases

    if cost_center:
        sql.append("AND e.cost_center IN %(cost_centers)s")
        params["cost_centers"] = tuple(cost_centers)

    if project:
        sql.append("AND COALESCE(e.project, contract_doc.project) = %(project)s")
        params["project"] = project

    if contract:
        sql.append("AND e.contract = %(contract)s")
        params["contract"] = contract

    if vendor:
        sql.append("AND r.vendor = %(vendor)s")
        params["vendor"] = vendor

    sql.append("ORDER BY e.name, r.idx")

    return frappe.db.sql("\n".join(sql), params, as_dict=True)


def _months_touched(period_start: datetime.date, period_end: datetime.date) -> list[int]:
    months = []
    current = datetime.date(period_start.year, period_start.month, 1)
    limit = datetime.date(period_end.year, period_end.month, 1)

    while current <= limit:
        months.append(current.month)
        if current.month == 12:
            current = datetime.date(current.year + 1, 1, 1)
        else:
            current = datetime.date(current.year, current.month + 1, 1)

    return months


def _month_periods_touched(period_start: datetime.date, period_end: datetime.date) -> list[datetime.date]:
    month_starts = []
    current = datetime.date(period_start.year, period_start.month, 1)
    limit = datetime.date(period_end.year, period_end.month, 1)

    while current <= limit:
        month_starts.append(current)
        if current.month == 12:
            current = datetime.date(current.year + 1, 1, 1)
        else:
            current = datetime.date(current.year, current.month + 1, 1)

    return month_starts


def _format_month_label(month_start: datetime.date, year_start: datetime.date, year_end: datetime.date) -> str:
    label = calendar.month_abbr[month_start.month]
    if year_start.year != year_end.year:
        return f"{label} {month_start.year}"
    return label
