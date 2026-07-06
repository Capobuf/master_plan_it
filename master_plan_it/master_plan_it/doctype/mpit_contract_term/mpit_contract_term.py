"""
MPIT Contract Term: child table for contract pricing periods.

Replicates VAT and monthly amount logic from MPIT Contract for per-term pricing.
"""

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.utils import flt

from master_plan_it import annualization, mpit_defaults, tax


class MPITContractTerm(Document):
    def validate(self):
        self._validate_billing_cycle()
        self._validate_dates()
        self._compute_vat_split()
        self._compute_monthly_amount()

    def _validate_billing_cycle(self) -> None:
        billing = (self.billing_cycle or "Monthly").strip()
        if billing not in {"Monthly", "Annual"}:
            frappe.throw(_("Billing Cycle must be Monthly or Annual."))
        self.billing_cycle = billing

    def _validate_dates(self) -> None:
        """Validate from_date and to_date if set."""
        if self.to_date and self.from_date:
            from frappe.utils import getdate
            if getdate(self.to_date) < getdate(self.from_date):
                frappe.throw(_("To Date cannot be before From Date."))

    def _compute_vat_split(self) -> None:
        """Compute net/vat/gross for this term amount."""
        default_vat = mpit_defaults.get_default_vat_rate()

        # Apply default if field is empty
        if self.vat_rate is None and default_vat is not None:
            self.vat_rate = default_vat

        # Validate and get final VAT rate
        final_vat_rate = tax.validate_strict_vat(
            self.amount,
            self.vat_rate,
            default_vat,
            field_label=_("Term Amount")
        )

        # Compute split
        net, vat, gross = tax.split_net_vat_gross(
            self.amount,
            final_vat_rate,
            bool(self.amount_includes_vat)
        )

        self.amount_net = flt(net, 2)
        self.amount_vat = flt(vat, 2)
        self.amount_gross = flt(gross, 2)

    def _compute_monthly_amount(self) -> None:
        """Compute monthly net equivalent for this term."""
        if self.amount_net is None:
            self.monthly_amount_net = None
            return

        billing = self.billing_cycle or "Monthly"
        self.monthly_amount_net = annualization.monthly_equivalent_net(
            self.amount_net or 0,
            billing,
            precision=2,
        )
