from __future__ import annotations

import datetime

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.model.naming import make_autoname, revert_series_if_last
from frappe.utils import add_days, add_years, flt, getdate

from master_plan_it import annualization, mpit_defaults, tax
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
            elif is_last and not term.to_date:
                term.to_date = add_days(add_years(getdate(term.from_date), 1), -1)

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
