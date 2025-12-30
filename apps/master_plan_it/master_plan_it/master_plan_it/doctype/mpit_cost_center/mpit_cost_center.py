# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from __future__ import annotations

from frappe.model.document import Document
from frappe.utils.nestedset import NestedSet


class MPITCostCenter(NestedSet, Document):
	"""Tree DocType for cost centers."""

