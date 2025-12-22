import frappe
import unittest
from master_plan_it.mpit_user_prefs import get_user_prefs, get_default_vat_rate, get_or_create


class TestMPITUserPrefs(unittest.TestCase):
    def setUp(self):
        # create a test user
        self.user_email = "test_mpit_user@example.com"
        if not frappe.db.exists("User", self.user_email):
            self.test_user = frappe.get_doc({
                "doctype": "User",
                "email": self.user_email,
                "first_name": "MPIT Test",
                "enabled": 1
            }).insert()
        else:
            self.test_user = frappe.get_doc("User", self.user_email)

        # ensure no prefs exist for the user
        frappe.db.delete("MPIT User Preferences", {"user": self.user_email})

        # create a second user for permission testing
        self.other_user = "test_mpit_other@example.com"
        if not frappe.db.exists("User", self.other_user):
            frappe.get_doc({
                "doctype": "User",
                "email": self.other_user,
                "first_name": "MPIT Other",
                "enabled": 1
            }).insert()

    def tearDown(self):
        frappe.db.delete("MPIT User Preferences", {"user": self.user_email})
        try:
            frappe.delete_doc("User", self.user_email, force=True)
        except Exception:
            pass
        try:
            frappe.delete_doc("User", self.other_user, force=True)
        except Exception:
            pass

    def test_get_and_nullable_vat(self):
        # create preferences with no default_vat_rate (nullable)
        prefs = frappe.get_doc({
            "doctype": "MPIT User Preferences",
            "user": self.user_email,
            "default_amount_includes_vat": 0
        }).insert()
        self.assertIsNone(get_default_vat_rate(self.user_email))

    def test_autoname_and_get_or_create(self):
        # get_or_create should create or return existing and name == user
        doc = get_or_create(self.user_email)
        self.assertEqual(doc.name, self.user_email)
        doc2 = get_or_create(self.user_email)
        self.assertEqual(doc.name, doc2.name)

    def test_permissions_owner_only_and_system_manager(self):
        # create preferences
        prefs = frappe.get_doc({
            "doctype": "MPIT User Preferences",
            "user": self.user_email
        }).insert()

        # attempt to update as another non-owner user -> expect permission error
        frappe.set_user(self.other_user)
        with self.assertRaises(frappe.PermissionError):
            doc = frappe.get_doc("MPIT User Preferences", prefs.name)
            doc.some_field = "hack"
            doc.save()

        # as System Manager should succeed
        frappe.set_user("Administrator")
        doc = frappe.get_doc("MPIT User Preferences", prefs.name)
        # make a harmless edit
        doc.show_attachments_in_print = 1
        doc.save()

        # reset back to Administrator for cleanup
        frappe.set_user("Administrator")
