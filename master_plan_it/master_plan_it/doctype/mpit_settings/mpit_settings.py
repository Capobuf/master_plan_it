# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from frappe.model.document import Document


class MPITSettings(Document):
	"""Singleton settings for Master Plan IT.
	
	Currency is managed at the Frappe site level (Global Defaults / System Settings).
	"""
	pass
