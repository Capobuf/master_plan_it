from __future__ import annotations

import frappe
from frappe import _
from frappe.model.document import Document
from frappe.utils import flt

from master_plan_it import mpit_defaults, tax


class MPITExpenseRow(Document):
    def validate(self):
        self._compute_input_amount()
        self._compute_vat_split()

    def _compute_input_amount(self) -> None:
        qty = flt(1 if self.qty in (None, "") else self.qty)
        unit_price = flt(self.unit_price or 0)

        # Unit Price makes Amount a derived total; without Unit Price, Amount remains manually entered.
        if unit_price:
            self.amount = flt(qty * unit_price, 2)
        else:
            self.amount = flt(self.amount or 0, 2)

    def _compute_vat_split(self) -> None:
        default_vat = mpit_defaults.get_default_vat_rate()

        if self.vat_rate is None and default_vat is not None:
            self.vat_rate = default_vat

        final_vat_rate = tax.validate_strict_vat(
            self.amount,
            self.vat_rate,
            default_vat,
            field_label=_("Expense Row Amount"),
        )

        net, vat, gross = tax.split_net_vat_gross(
            self.amount,
            final_vat_rate,
            bool(self.amount_includes_vat),
        )

        self.amount_net = flt(net, 2)
        self.amount_vat = flt(vat, 2)
        self.amount_gross = flt(gross, 2)
