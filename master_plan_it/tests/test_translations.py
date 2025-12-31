# -*- coding: utf-8 -*-
"""Tests to verify workspace/UI labels are translated in Italian when translations exist.

These tests insert Translation records (language=it) for a small set of workspace labels,
clear the translation cache and assert that `frappe._` returns the expected Italian text.
"""

import frappe
from frappe.tests.utils import FrappeTestCase

class TestTranslations(FrappeTestCase):
	def setUp(self):
		# Ensure tests run as Administrator so we can insert Translation records
		frappe.set_user("Administrator")

	def test_workspace_labels_translate(self):
		translations = {
			"Setup": "Impostazioni",
			"Setup & Planning": "Impostazioni e pianificazione",
			"Categories": "Categorie",
			"Vendors": "Fornitori",
			"Contracts": "Contratti",
			"Expired Contracts": "Contratti scaduti",
			"Quick Actions": "Azioni rapide",
			"Quick Links": "Collegamenti rapidi",
			"Your Shortcuts": "I tuoi collegamenti",
			"More": "Altro",
		}

		# Insert Translation records if missing
		for src, tr in translations.items():
			exists = frappe.db.exists("Translation", {"language": "it", "source_text": src})
			if not exists:
				frappe.get_doc({
					"doctype": "Translation",
					"language": "it",
					"source_text": src,
					"translated_text": tr,
				}).insert(ignore_permissions=True)

		# Clear translation cache and assert translations are used
		frappe.translate.clear_cache()
		frappe.local.lang = "it"
		for src, tr in translations.items():
			self.assertEqual(frappe._(src), tr)
