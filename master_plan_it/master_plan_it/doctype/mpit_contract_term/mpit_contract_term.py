"""
MPIT Contract Term: child table for contract pricing periods.

Replicates VAT and monthly amount logic from MPIT Contract for per-term pricing.
"""

from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.utils import flt

from master_plan_it import mpit_defaults, tax


class MPITContractTerm(Document):
    def validate(self):
        self._validate_dates()
        self._compute_vat_split()
        self._compute_monthly_amount()

    def _validate_dates(self) -> None:
        """Validate from_date and to_date if set."""
        if self.to_date and self.from_date:
            from frappe.utils import getdate
            if getdate(self.to_date) < getdate(self.from_date):
                frappe.throw(_("To Date cannot be before From Date."))

    def _compute_vat_split(self) -> None:
        """Compute net/vat/gross - same logic as Contract.current_amount."""
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
        """Compute monthly net equivalent - same logic as Contract."""
        if self.amount_net is None:
            self.monthly_amount_net = None
            return

        billing = self.billing_cycle or "Monthly"
        if billing == "Quarterly":
            self.monthly_amount_net = flt((self.amount_net or 0) * 4 / 12, 2)
        elif billing == "Annual":
            self.monthly_amount_net = flt((self.amount_net or 0) / 12, 2)
        else:
            # Monthly and "Other" default to same value
            self.monthly_amount_net = flt(self.amount_net or 0, 2)
