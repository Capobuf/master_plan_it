from __future__ import annotations

import calendar
import datetime
from collections import defaultdict

import frappe
from frappe.utils import flt, getdate

from master_plan_it import annualization, tax

ACTIVE_CONTRACT_STATUSES = {"Active", "Pending Renewal", "Renewed"}
ACTIVE_EXPENSE_STATES = {"Open", "Closed"}
ACTIVE_ROW_STATES = {"Active"}


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
    if period_start > period_end:
        return 0.0

    overlap_months = annualization.overlap_months(period_start, period_end, year_start, year_end)
    if overlap_months <= 0:
        return 0.0

    monthly_net = _monthly_from_cycle(amount_net, billing_cycle)
    return flt(monthly_net * overlap_months, 2)


def allocate_expense_row_to_months(row: dict, year_start: datetime.date, year_end: datetime.date) -> dict[int, float]:
    amount = flt(row.get("amount_net"), 2)
    if amount == 0:
        return {}

    spend_date = row.get("spend_date")
    if spend_date:
        spend = getdate(spend_date)
        if year_start <= spend <= year_end:
            return {spend.month: amount}
        return {}

    start_date = row.get("start_date")
    end_date = row.get("end_date")
    if not start_date or not end_date:
        return {}

    period_start = max(getdate(start_date), year_start)
    period_end = min(getdate(end_date), year_end)
    if period_start > period_end:
        return {}

    months = _months_touched(period_start, period_end)
    if not months:
        return {}

    distribution = (row.get("distribution") or "all").lower()
    if distribution == "start":
        return {months[0]: amount}
    if distribution == "end":
        return {months[-1]: amount}

    per_month = flt(amount / len(months), 6)
    out: dict[int, float] = {}
    for month in months:
        out[month] = flt(out.get(month, 0) + per_month, 6)
    return out


def get_contract_forecast_totals(year: str | int, cost_center: str | None = None, contract: str | None = None) -> dict:
    year_int = _resolve_year_int(year)
    year_start, year_end = annualization.get_year_bounds(year_int)

    filters: dict = {"status": ["in", list(ACTIVE_CONTRACT_STATUSES)]}
    if cost_center:
        filters["cost_center"] = cost_center
    if contract:
        filters["name"] = contract

    contracts = frappe.get_all(
        "MPIT Contract",
        filters=filters,
        fields=[
            "name",
            "cost_center",
            "start_date",
            "end_date",
            "billing_cycle",
            "current_amount",
            "current_amount_net",
            "current_amount_includes_vat",
            "vat_rate",
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
) -> dict:
    rows = _get_active_rows(
        year,
        cost_center=cost_center,
        project=project,
        contract=contract,
        expense_kind="Ordinary",
        row_phases=("Estimate", "Quote"),
    )

    estimate_total = 0.0
    quote_total = 0.0
    for row in rows:
        amount = flt(row.get("amount_net"), 2)
        if row.get("row_phase") == "Estimate":
            estimate_total += amount
        elif row.get("row_phase") == "Quote":
            quote_total += amount

    return {
        "estimate_total": flt(estimate_total, 2),
        "quote_total": flt(quote_total, 2),
        "expense_forecast_total": flt(estimate_total + quote_total, 2),
    }


def get_actual_totals(
    year: str | int,
    cost_center: str | None = None,
    project: str | None = None,
    contract: str | None = None,
) -> dict:
    rows = _get_active_rows(
        year,
        cost_center=cost_center,
        project=project,
        contract=contract,
        expense_kind="Ordinary",
        row_phases=("Actual",),
    )

    actual_total = 0.0
    actual_on_plafond = 0.0
    actual_extra = 0.0

    for row in rows:
        amount = flt(row.get("amount_net"), 2)
        actual_total += amount
        if row.get("uses_plafond"):
            actual_on_plafond += amount
        elif row.get("is_extra"):
            actual_extra += amount

    return {
        "actual_total": flt(actual_total, 2),
        "actual_on_plafond": flt(actual_on_plafond, 2),
        "actual_extra": flt(actual_extra, 2),
    }


def get_plafond_totals(year: str | int, cost_center: str | None = None) -> dict:
    year_int = _resolve_year_int(year)

    filters: dict = {
        "year": str(year_int),
        "expense_kind": "Plafond",
        "workflow_state": ["!=", "Cancelled"],
    }
    if cost_center:
        filters["cost_center"] = cost_center

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
              AND e.workflow_state IN ('Open', 'Closed')
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


def get_cost_center_financial_summary(year: str | int, cost_center: str) -> dict:
    contract_totals = get_contract_forecast_totals(year, cost_center=cost_center)
    expense_forecast = get_expense_forecast_totals(year, cost_center=cost_center)
    actual_totals = get_actual_totals(year, cost_center=cost_center)
    plafond_totals = get_plafond_totals(year, cost_center=cost_center)

    forecast_total = flt(
        contract_totals.get("contract_forecast_total", 0) + expense_forecast.get("expense_forecast_total", 0),
        2,
    )

    return {
        "cost_center": cost_center,
        "forecast_contracts": flt(contract_totals.get("contract_forecast_total", 0), 2),
        "forecast_estimate": flt(expense_forecast.get("estimate_total", 0), 2),
        "forecast_quote": flt(expense_forecast.get("quote_total", 0), 2),
        "forecast_total": forecast_total,
        "actual_on_plafond": flt(actual_totals.get("actual_on_plafond", 0), 2),
        "actual_extra": flt(actual_totals.get("actual_extra", 0), 2),
        "actual_total": flt(actual_totals.get("actual_total", 0), 2),
        "plafond": flt(plafond_totals.get("plafond_total", 0), 2),
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


def get_overview_dataset(year: str | int, cost_center: str | None = None) -> dict:
    year_int = _resolve_year_int(year)

    if cost_center:
        cost_centers = [cost_center]
    else:
        from_contracts = frappe.get_all(
            "MPIT Contract",
            filters={"status": ["in", list(ACTIVE_CONTRACT_STATUSES)]},
            pluck="cost_center",
            limit=None,
        )
        from_expenses = frappe.get_all(
            "MPIT Expense",
            filters={"year": str(year_int), "workflow_state": ["in", list(ACTIVE_EXPENSE_STATES)]},
            pluck="cost_center",
            limit=None,
        )
        cost_centers = sorted({cc for cc in (from_contracts + from_expenses) if cc})

    rows = []
    for cc in cost_centers:
        rows.append(get_cost_center_financial_summary(year_int, cc))

    summary = {
        "forecast_contracts": flt(sum(row["forecast_contracts"] for row in rows), 2),
        "forecast_estimate": flt(sum(row["forecast_estimate"] for row in rows), 2),
        "forecast_quote": flt(sum(row["forecast_quote"] for row in rows), 2),
        "forecast_total": flt(sum(row["forecast_total"] for row in rows), 2),
        "actual_on_plafond": flt(sum(row["actual_on_plafond"] for row in rows), 2),
        "actual_extra": flt(sum(row["actual_extra"] for row in rows), 2),
        "actual_total": flt(sum(row["actual_total"] for row in rows), 2),
        "plafond": flt(sum(row["plafond"] for row in rows), 2),
        "remaining": flt(sum(row["remaining"] for row in rows), 2),
        "over": flt(sum(row["over"] for row in rows), 2),
    }

    return {"year": str(year_int), "rows": rows, "summary": summary}


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

    if not project:
        contract_rows = _get_active_contracts(cost_center=cost_center, contract=contract)
        for contract_row in contract_rows:
            monthly_map = _contract_monthly_allocation(contract_row, year_start, year_end)
            for month, value in monthly_map.items():
                forecast_by_month[month] += flt(value, 6)

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
        for month, value in monthly_map.items():
            forecast_by_month[month] += flt(value, 6)

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
        for month, value in monthly_map.items():
            actual_by_month[month] += flt(value, 6)

    months = []
    forecast_total = 0.0
    actual_total = 0.0

    for month in range(1, 13):
        forecast_value = flt(forecast_by_month.get(month, 0), 2)
        actual_value = flt(actual_by_month.get(month, 0), 2)
        forecast_total += forecast_value
        actual_total += actual_value

        months.append(
            {
                "month_index": month,
                "month": calendar.month_abbr[month],
                "forecast": forecast_value,
                "actual": actual_value,
                "delta": flt(forecast_value - actual_value, 2),
            }
        )

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


def _monthly_from_cycle(amount_net: float, billing_cycle: str | None) -> float:
    amount_net = flt(amount_net, 2)
    cycle = (billing_cycle or "Monthly").strip()

    if cycle == "Quarterly":
        return flt(amount_net * 4 / 12, 6)
    if cycle == "Annual":
        return flt(amount_net / 12, 6)
    return flt(amount_net, 6)


def _contract_header_amount_net(contract_row) -> float:
    if contract_row.current_amount_net is not None:
        return flt(contract_row.current_amount_net, 2)

    amount = flt(contract_row.current_amount or 0, 2)
    if amount == 0:
        return 0.0

    net, _vat, _gross = tax.split_net_vat_gross(
        amount,
        contract_row.vat_rate,
        bool(contract_row.current_amount_includes_vat),
    )
    return flt(net, 2)


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
            if contract_row.start_date:
                period_start = max(period_start, getdate(contract_row.start_date))
            if contract_row.end_date:
                period_end = min(period_end, getdate(contract_row.end_date))

            if period_start > period_end:
                continue

            overlap_months = annualization.overlap_months(period_start, period_end, year_start, year_end)
            if overlap_months <= 0:
                continue

            impacted = True
            term_amount_net = flt(term.amount_net if term.amount_net is not None else term.amount, 2)
            monthly_net = _monthly_from_cycle(term_amount_net, term.billing_cycle)
            total += flt(monthly_net * overlap_months, 2)

        if not impacted:
            return 0.0
        return flt(total, 2)

    header_amount_net = _contract_header_amount_net(contract_row)
    if header_amount_net == 0:
        return 0.0

    contract_start = getdate(contract_row.start_date) if contract_row.start_date else year_start
    contract_end = getdate(contract_row.end_date) if contract_row.end_date else year_end

    return allocate_contract_amount_to_year(
        header_amount_net,
        contract_row.billing_cycle,
        contract_start,
        contract_end,
        year_start,
        year_end,
    )


def _get_contract_terms(contract_name: str) -> list:
    terms = frappe.get_all(
        "MPIT Contract Term",
        filters={"parent": contract_name, "parenttype": "MPIT Contract", "parentfield": "terms"},
        fields=["name", "from_date", "to_date", "amount", "amount_net", "billing_cycle"],
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
            "start_date",
            "end_date",
            "billing_cycle",
            "current_amount",
            "current_amount_net",
            "current_amount_includes_vat",
            "vat_rate",
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
            if contract_row.start_date:
                period_start = max(period_start, getdate(contract_row.start_date))
            if contract_row.end_date:
                period_end = min(period_end, getdate(contract_row.end_date))
            if period_start > period_end:
                continue

            term_amount_net = flt(term.amount_net if term.amount_net is not None else term.amount, 2)
            monthly_net = _monthly_from_cycle(term_amount_net, term.billing_cycle)

            for month in _months_touched(period_start, period_end):
                monthly_map[month] += flt(monthly_net, 6)
        return monthly_map

    header_amount_net = _contract_header_amount_net(contract_row)
    if header_amount_net == 0:
        return monthly_map

    monthly_net = _monthly_from_cycle(header_amount_net, contract_row.billing_cycle)
    period_start = getdate(contract_row.start_date) if contract_row.start_date else year_start
    period_end = getdate(contract_row.end_date) if contract_row.end_date else year_end
    period_start = max(period_start, year_start)
    period_end = min(period_end, year_end)

    if period_start > period_end:
        return monthly_map

    for month in _months_touched(period_start, period_end):
        monthly_map[month] += flt(monthly_net, 6)

    return monthly_map


def _get_active_rows(
    year: str | int,
    cost_center: str | None = None,
    project: str | None = None,
    contract: str | None = None,
    expense_kind: str = "Ordinary",
    row_phases: tuple[str, ...] | None = None,
) -> list[dict]:
    year_int = _resolve_year_int(year)

    sql = [
        """
        SELECT
            e.name AS expense,
            e.cost_center,
            e.project,
            e.contract,
            e.expense_kind,
            e.uses_plafond,
            e.is_extra,
            r.name AS row_name,
            r.row_phase,
            r.amount_net,
            r.spend_date,
            r.start_date,
            r.end_date,
            r.distribution
        FROM `tabMPIT Expense Row` r
        INNER JOIN `tabMPIT Expense` e ON e.name = r.parent
        WHERE e.year = %(year)s
          AND e.expense_kind = %(expense_kind)s
          AND e.workflow_state IN ('Open', 'Closed')
          AND r.row_state = 'Active'
        """
    ]

    params: dict = {"year": str(year_int), "expense_kind": expense_kind}

    if row_phases:
        sql.append("AND r.row_phase IN %(row_phases)s")
        params["row_phases"] = row_phases

    if cost_center:
        sql.append("AND e.cost_center = %(cost_center)s")
        params["cost_center"] = cost_center

    if project:
        sql.append("AND e.project = %(project)s")
        params["project"] = project

    if contract:
        sql.append("AND e.contract = %(contract)s")
        params["contract"] = contract

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
