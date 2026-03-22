"""
Acceptance tests for MPIT business flows.

Covers gaps not addressed by existing doctype-level tests:
- Actual Entry entry-kind validation rules (happy path + all invalid combinations)
- Verified entry immutability
- One-off Actual Entry appearing in Live budget after refresh
- Planned Item date/spend_date validation
- Planned Item coverage field consistency
- Project totals (planned_total_net, variance_net, utilization_pct)
- Addendum pre-submit validation (reason, approved snapshot, allowance line)
- Budget total_amount_monthly == total_amount_net / 12

These tests use the real Frappe document lifecycle (insert/save/submit/cancel),
triggering the same validations a user would encounter.
"""

from __future__ import annotations

import datetime

import frappe
from frappe.tests.utils import FrappeTestCase
from frappe.utils import add_days, flt, nowdate


# ---------------------------------------------------------------------------
# Shared helpers
# ---------------------------------------------------------------------------

def _ensure_year(year: str) -> None:
    if not frappe.db.exists("MPIT Year", year):
        frappe.get_doc({
            "doctype": "MPIT Year",
            "year": int(year),
            "start_date": f"{year}-01-01",
            "end_date": f"{year}-12-31",
        }).insert(ignore_if_duplicate=True)


def _ensure_cost_center(name: str) -> frappe.Document:
    if not frappe.db.exists("MPIT Cost Center", name):
        frappe.get_doc({
            "doctype": "MPIT Cost Center",
            "cost_center_name": name,
            "is_group": 0,
        }).insert(ignore_if_duplicate=True)
    return frappe.get_doc("MPIT Cost Center", name)


def _ensure_vendor(name: str) -> frappe.Document:
    if not frappe.db.exists("MPIT Vendor", name):
        frappe.get_doc({"doctype": "MPIT Vendor", "vendor_name": name}).insert(
            ignore_if_duplicate=True
        )
    return frappe.get_doc("MPIT Vendor", name)


def _make_contract(vendor_name: str, cc_name: str, amount: float = 100.0, year: str = "2040") -> frappe.Document:
    vendor = _ensure_vendor(vendor_name)
    cc = _ensure_cost_center(cc_name)
    _ensure_year(year)
    return frappe.get_doc({
        "doctype": "MPIT Contract",
        "description": f"Contract {frappe.generate_hash(length=6)}",
        "vendor": vendor.name,
        "cost_center": cc.name,
        "status": "Active",
        "terms": [{
            "from_date": f"{year}-01-01",
            "amount": amount,
            "amount_includes_vat": 0,
            "vat_rate": 0,
            "billing_cycle": "Monthly",
        }],
    }).insert()


def _make_project(cc_name: str, year: str = "2040") -> frappe.Document:
    _ensure_year(year)
    cc = _ensure_cost_center(cc_name)
    return frappe.get_doc({
        "doctype": "MPIT Project",
        "title": f"Project {frappe.generate_hash(length=6)}",
        "cost_center": cc.name,
    }).insert()


def _make_live_budget(year: str) -> frappe.Document:
    _ensure_year(year)
    existing = frappe.db.get_value("MPIT Budget", {"year": year, "budget_type": "Live"}, "name")
    if existing:
        doc = frappe.get_doc("MPIT Budget", existing)
        doc.reload()
        return doc
    return frappe.get_doc({
        "doctype": "MPIT Budget",
        "year": year,
        "budget_type": "Live",
        "title": f"Live {year}",
    }).insert()


# ---------------------------------------------------------------------------
# Section 1: Actual Entry - entry kind validation
# ---------------------------------------------------------------------------

class TestActualEntryKindValidation(FrappeTestCase):
    """User-visible validation rules for MPIT Actual Entry entry_kind."""

    YEAR = "2040"
    POSTING_DATE = "2040-06-15"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"AE-CC-{frappe.generate_hash(length=6)}")
        self.contract = _make_contract(
            f"AE-Vendor-{frappe.generate_hash(length=6)}",
            self.cc.name,
            amount=200.0,
            year=self.YEAR,
        )
        self.project = _make_project(self.cc.name, year=self.YEAR)

    # --- Invalid cases (user sees blocking error) ---

    def test_delta_entry_requires_link(self):
        """Delta entry with no contract and no project must be rejected."""
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "Delta",
            "cost_center": self.cc.name,
            "amount": 50,
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_delta_entry_rejects_both_contract_and_project(self):
        """Delta entry linking both a contract AND a project must be rejected.

        The rule 'contract XOR project' prevents ambiguous attribution.
        """
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "Delta",
            "contract": self.contract.name,
            "project": self.project.name,
            "amount": 50,
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_oneoff_entry_with_project_rejected(self):
        """One-off entry must not link to a project; 'Delta' should be used instead."""
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "One-off",
            "project": self.project.name,
            "cost_center": self.cc.name,
            "amount": 50,
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_oneoff_entry_with_contract_rejected(self):
        """One-off entry must not link to a contract."""
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "One-off",
            "contract": self.contract.name,
            "cost_center": self.cc.name,
            "amount": 50,
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_allowance_spend_with_contract_rejected(self):
        """Allowance Spend must not reference a contract."""
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "Allowance Spend",
            "contract": self.contract.name,
            "cost_center": self.cc.name,
            "amount": 50,
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_allowance_spend_with_project_rejected(self):
        """Allowance Spend must not reference a project."""
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "Allowance Spend",
            "project": self.project.name,
            "cost_center": self.cc.name,
            "amount": 50,
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_missing_posting_date_rejected(self):
        """Posting Date is required — it derives MPIT Year."""
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "entry_kind": "Allowance Spend",
            "cost_center": self.cc.name,
            "amount": 50,
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_posting_date_outside_mpit_year_rejected(self):
        """Posting date that falls in no MPIT Year must be rejected with a helpful message."""
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": "1990-01-01",  # no MPIT Year covers 1990
            "entry_kind": "Allowance Spend",
            "cost_center": self.cc.name,
            "amount": 50,
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    # --- Happy path (user can complete the flow) ---

    def test_delta_entry_with_contract_happy_path(self):
        """Valid Delta entry linked to a contract inserts successfully.

        Asserts: year derived, amount_net computed, entry_kind preserved.
        """
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "Delta",
            "contract": self.contract.name,
            "amount": 100,
            "vat_rate": 0,
        })
        doc.insert()

        self.assertEqual(doc.year, self.YEAR)
        self.assertEqual(doc.entry_kind, "Delta")
        self.assertEqual(doc.contract, self.contract.name)
        self.assertAlmostEqual(flt(doc.amount_net), 100.0, places=2)
        self.assertEqual(doc.cost_center, self.cc.name, "cost_center auto-filled from contract")

    def test_delta_entry_with_project_happy_path(self):
        """Valid Delta entry linked to a project inserts successfully."""
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "Delta",
            "project": self.project.name,
            "amount": 75,
            "vat_rate": 0,
        })
        doc.insert()

        self.assertEqual(doc.year, self.YEAR)
        self.assertEqual(doc.project, self.project.name)
        self.assertAlmostEqual(flt(doc.amount_net), 75.0, places=2)

    def test_oneoff_entry_happy_path(self):
        """Valid One-off entry (no contract/project link) inserts successfully."""
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "One-off",
            "cost_center": self.cc.name,
            "amount": 500,
            "vat_rate": 0,
            "description": "One-off server upgrade",
        })
        doc.insert()

        self.assertEqual(doc.year, self.YEAR)
        self.assertEqual(doc.entry_kind, "One-off")
        self.assertAlmostEqual(flt(doc.amount_net), 500.0, places=2)

    def test_oneoff_entry_vat_split_correct(self):
        """One-off entry with VAT: net = gross / (1 + rate), vat = gross - net."""
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "One-off",
            "cost_center": self.cc.name,
            "amount": 1220,
            "amount_includes_vat": 1,
            "vat_rate": 22,
            "description": "VAT included one-off",
        })
        doc.insert()

        # 1220 gross at 22% → net = 1000, vat = 220
        self.assertAlmostEqual(flt(doc.amount_net), 1000.0, places=2)
        self.assertAlmostEqual(flt(doc.amount_vat), 220.0, places=2)
        self.assertAlmostEqual(flt(doc.amount_gross), 1220.0, places=2)

    def test_allowance_spend_happy_path(self):
        """Valid Allowance Spend (no contract/project) inserts successfully."""
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "Allowance Spend",
            "cost_center": self.cc.name,
            "amount": 300,
            "vat_rate": 0,
        })
        doc.insert()

        self.assertEqual(doc.entry_kind, "Allowance Spend")
        self.assertAlmostEqual(flt(doc.amount_net), 300.0, places=2)


# ---------------------------------------------------------------------------
# Section 2: Verified entry immutability
# ---------------------------------------------------------------------------

class TestVerifiedEntryImmutability(FrappeTestCase):
    """Verified Actual Entries must be read-only except for status field."""

    YEAR = "2041"
    POSTING_DATE = "2041-03-10"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"VE-CC-{frappe.generate_hash(length=6)}")
        # Create and "verify" an entry via db.set_value (only way in test
        # because role enforcement blocks API-level status change without vCIO Manager).
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "Allowance Spend",
            "cost_center": self.cc.name,
            "amount": 100,
            "vat_rate": 0,
        })
        doc.insert()
        # Bypass workflow to set Verified (test setup only – not business logic test)
        frappe.db.set_value("MPIT Actual Entry", doc.name, "status", "Verified")
        self.entry_name = doc.name

    def test_verified_entry_posting_date_immutable(self):
        """Changing posting_date on a Verified entry must raise ValidationError."""
        doc = frappe.get_doc("MPIT Actual Entry", self.entry_name)
        doc.posting_date = "2041-04-01"
        with self.assertRaises(frappe.ValidationError):
            doc.save()

    def test_verified_entry_amount_immutable(self):
        """Changing amount on a Verified entry must raise ValidationError."""
        doc = frappe.get_doc("MPIT Actual Entry", self.entry_name)
        doc.amount = 999
        with self.assertRaises(frappe.ValidationError):
            doc.save()

    def test_verified_entry_entry_kind_immutable(self):
        """Changing entry_kind on a Verified entry must raise ValidationError."""
        doc = frappe.get_doc("MPIT Actual Entry", self.entry_name)
        doc.entry_kind = "One-off"
        with self.assertRaises(frappe.ValidationError):
            doc.save()

    def test_verified_entry_description_immutable(self):
        """Changing description on a Verified entry must raise ValidationError."""
        doc = frappe.get_doc("MPIT Actual Entry", self.entry_name)
        doc.description = "tampered"
        with self.assertRaises(frappe.ValidationError):
            doc.save()


# ---------------------------------------------------------------------------
# Section 3: One-off entry feeds Live budget
# ---------------------------------------------------------------------------

class TestOneOffEntryFeedsBudget(FrappeTestCase):
    """Verified One-off Actual Entries must appear as budget lines after refresh."""

    YEAR = "2042"
    POSTING_DATE = "2042-07-20"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"OO-CC-{frappe.generate_hash(length=6)}")

    def test_verified_oneoff_appears_in_budget_after_refresh(self):
        """A Verified One-off entry must generate a budget line on refresh."""
        # Create and verify the entry
        entry = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "One-off",
            "cost_center": self.cc.name,
            "amount": 800,
            "vat_rate": 0,
            "description": "One-off infra purchase",
        })
        entry.insert()
        # Set to Verified (test setup via db.set_value — bypasses role check)
        frappe.db.set_value("MPIT Actual Entry", entry.name, "status", "Verified")
        entry.reload()

        budget = _make_live_budget(self.YEAR)
        budget.refresh_from_sources()
        budget.reload()

        source_key = f"ONEOFF::{entry.name}"
        matching = [ln for ln in budget.lines if getattr(ln, "source_key", "") == source_key]

        self.assertEqual(len(matching), 1, "Verified One-off entry must produce exactly one budget line")
        line = matching[0]
        self.assertAlmostEqual(flt(line.monthly_amount), 800.0, places=2)
        self.assertEqual(line.line_kind, "One-off")
        self.assertEqual(line.cost_center, self.cc.name)

    def test_unverified_oneoff_excluded_from_budget(self):
        """A Recorded (unverified) One-off entry must NOT generate a budget line."""
        entry = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": self.POSTING_DATE,
            "entry_kind": "One-off",
            "cost_center": self.cc.name,
            "amount": 400,
            "vat_rate": 0,
            "description": "Unverified purchase",
        })
        entry.insert()
        # Leave at default "Recorded" status

        budget = _make_live_budget(self.YEAR)
        budget.refresh_from_sources()
        budget.reload()

        source_key = f"ONEOFF::{entry.name}"
        matching = [ln for ln in budget.lines if getattr(ln, "source_key", "") == source_key]
        self.assertEqual(len(matching), 0, "Unverified One-off must not appear in budget")


# ---------------------------------------------------------------------------
# Section 4: Planned Item validation
# ---------------------------------------------------------------------------

class TestPlannedItemValidation(FrappeTestCase):
    """User-visible Planned Item validation rules."""

    YEAR = "2043"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"PI-CC-{frappe.generate_hash(length=6)}")
        self.project = _make_project(self.cc.name, year=self.YEAR)

    def test_past_spend_date_rejected(self):
        """spend_date in the past must be rejected — spend_date must be a future commitment."""
        yesterday = add_days(nowdate(), -1)
        doc = frappe.get_doc({
            "doctype": "MPIT Planned Item",
            "project": self.project.name,
            "description": "Past spend date",
            "amount": 100,
            "spend_date": yesterday,
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_dates_required_without_spend_date(self):
        """start_date and end_date are mandatory when spend_date is not set."""
        doc = frappe.get_doc({
            "doctype": "MPIT Planned Item",
            "project": self.project.name,
            "description": "No dates",
            "amount": 100,
            "vat_rate": 0,
            # No spend_date, no start_date, no end_date
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_start_date_without_end_date_rejected(self):
        """start_date alone (no end_date, no spend_date) must be rejected."""
        doc = frappe.get_doc({
            "doctype": "MPIT Planned Item",
            "project": self.project.name,
            "description": "Only start",
            "amount": 100,
            "start_date": f"{self.YEAR}-01-01",
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_end_date_before_start_date_rejected(self):
        """end_date before start_date must be rejected."""
        doc = frappe.get_doc({
            "doctype": "MPIT Planned Item",
            "project": self.project.name,
            "description": "Inverted dates",
            "amount": 100,
            "start_date": f"{self.YEAR}-06-01",
            "end_date": f"{self.YEAR}-01-01",
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_covered_by_type_without_name_rejected(self):
        """Setting covered_by_type without covered_by_name must be rejected."""
        doc = frappe.get_doc({
            "doctype": "MPIT Planned Item",
            "project": self.project.name,
            "description": "Partial coverage",
            "amount": 100,
            "start_date": f"{self.YEAR}-01-01",
            "end_date": f"{self.YEAR}-12-31",
            "covered_by_type": "MPIT Contract",
            # covered_by_name intentionally omitted
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_covered_by_name_without_type_rejected(self):
        """Setting covered_by_name without covered_by_type must be rejected."""
        doc = frappe.get_doc({
            "doctype": "MPIT Planned Item",
            "project": self.project.name,
            "description": "Name without type",
            "amount": 100,
            "start_date": f"{self.YEAR}-01-01",
            "end_date": f"{self.YEAR}-12-31",
            "covered_by_name": "CONTR-01",
            # covered_by_type intentionally omitted
            "vat_rate": 0,
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_planned_item_vat_split_computed(self):
        """Planned Item with VAT must compute net/vat/gross on save."""
        doc = frappe.get_doc({
            "doctype": "MPIT Planned Item",
            "project": self.project.name,
            "description": "VAT item",
            "amount": 1220,
            "amount_includes_vat": 1,
            "vat_rate": 22,
            "start_date": f"{self.YEAR}-01-01",
            "end_date": f"{self.YEAR}-12-31",
        })
        doc.insert()

        self.assertAlmostEqual(flt(doc.amount_net), 1000.0, places=2)
        self.assertAlmostEqual(flt(doc.amount_vat), 220.0, places=2)
        self.assertAlmostEqual(flt(doc.amount_gross), 1220.0, places=2)


# ---------------------------------------------------------------------------
# Section 5: Project totals computation
# ---------------------------------------------------------------------------

class TestProjectTotalsComputation(FrappeTestCase):
    """Project financial totals must match the formula defined in mpit_project.py.

    Formula (from source):
      planned_total_net = SUM(Quote.amount_net) if quotes exist, else SUM(Estimate.amount_net)
      actual_total_net  = SUM(Delta.amount_net WHERE status=Verified)
      expected_total_net = uncovered_quotes (or estimates) + actual_total_net
      variance_net      = planned_total_net - expected_total_net
      utilization_pct   = (actual_total_net / planned_total_net) * 100
    """

    YEAR = "2044"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"PT-CC-{frappe.generate_hash(length=6)}")

    def _make_estimate_item(self, project_name: str, amount: float) -> frappe.Document:
        doc = frappe.get_doc({
            "doctype": "MPIT Planned Item",
            "project": project_name,
            "description": f"Estimate {frappe.generate_hash(length=4)}",
            "item_type": "Estimate",
            "amount": amount,
            "start_date": f"{self.YEAR}-01-01",
            "end_date": f"{self.YEAR}-12-31",
            "vat_rate": 0,
        })
        doc.insert()
        return doc

    def test_project_planned_total_from_estimate_items(self):
        """planned_total_net equals the sum of non-cancelled Estimate item amount_net."""
        project = frappe.get_doc({
            "doctype": "MPIT Project",
            "title": f"PT Estimate {frappe.generate_hash(length=6)}",
            "cost_center": self.cc.name,
        }).insert()

        self._make_estimate_item(project.name, 400)
        self._make_estimate_item(project.name, 600)

        project.reload()
        # project.save() triggers _compute_project_totals()
        project.save()
        project.reload()

        self.assertAlmostEqual(flt(project.planned_total_net), 1000.0, places=2)

    def test_project_actual_total_from_verified_delta(self):
        """actual_total_net is the sum of Verified Delta entries for this project."""
        project = frappe.get_doc({
            "doctype": "MPIT Project",
            "title": f"PT Actual {frappe.generate_hash(length=6)}",
            "cost_center": self.cc.name,
        }).insert()
        self._make_estimate_item(project.name, 1000)

        # Create a Verified Delta entry for the project
        entry = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": f"{self.YEAR}-03-01",
            "entry_kind": "Delta",
            "project": project.name,
            "amount": 250,
            "vat_rate": 0,
        })
        entry.insert()
        # Set Verified via db.set_value (test setup bypass)
        frappe.db.set_value("MPIT Actual Entry", entry.name, "status", "Verified")

        project.reload()
        project.save()
        project.reload()

        self.assertAlmostEqual(flt(project.actual_total_net), 250.0, places=2)

    def test_project_variance_formula(self):
        """variance_net == planned_total_net - expected_total_net."""
        project = frappe.get_doc({
            "doctype": "MPIT Project",
            "title": f"PT Variance {frappe.generate_hash(length=6)}",
            "cost_center": self.cc.name,
        }).insert()
        self._make_estimate_item(project.name, 1000)

        # No verified deltas → expected_total_net equals uncovered items
        project.reload()
        project.save()
        project.reload()

        expected_variance = flt(project.planned_total_net) - flt(project.expected_total_net)
        self.assertAlmostEqual(
            flt(project.variance_net), expected_variance, places=2,
            msg="variance_net must equal planned_total_net - expected_total_net"
        )

    def test_project_utilization_formula(self):
        """utilization_pct == (actual_total_net / planned_total_net) * 100."""
        project = frappe.get_doc({
            "doctype": "MPIT Project",
            "title": f"PT Utilization {frappe.generate_hash(length=6)}",
            "cost_center": self.cc.name,
        }).insert()
        self._make_estimate_item(project.name, 500)

        # Add a Verified Delta entry
        entry = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": f"{self.YEAR}-05-01",
            "entry_kind": "Delta",
            "project": project.name,
            "amount": 100,
            "vat_rate": 0,
        })
        entry.insert()
        frappe.db.set_value("MPIT Actual Entry", entry.name, "status", "Verified")

        project.reload()
        project.save()
        project.reload()

        if flt(project.planned_total_net) > 0:
            expected_pct = flt(project.actual_total_net) / flt(project.planned_total_net) * 100
            self.assertAlmostEqual(
                flt(project.utilization_pct), expected_pct, places=1,
                msg="utilization_pct must equal (actual / planned) * 100"
            )


# ---------------------------------------------------------------------------
# Section 6: Addendum pre-submit validation
# ---------------------------------------------------------------------------

class TestAddendumValidation(FrappeTestCase):
    """Addendum must validate reason, approved snapshot, and allowance line before submit."""

    YEAR = "2045"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"ADD-CC-{frappe.generate_hash(length=6)}")

    def _make_approved_snapshot_with_allowance(self) -> frappe.Document:
        """Create a minimal approved (submitted) Snapshot with an Allowance line."""
        frappe.flags.allow_live_manual_lines = True
        snap = frappe.get_doc({
            "doctype": "MPIT Budget",
            "year": self.YEAR,
            "budget_type": "Snapshot",
            "title": f"Snap {frappe.generate_hash(length=4)}",
            "workflow_state": "Draft",
            "lines": [{
                "doctype": "MPIT Budget Line",
                "cost_center": self.cc.name,
                "line_kind": "Allowance",
                "monthly_amount": 500,
                "recurrence_rule": "Monthly",
                "amount_includes_vat": 0,
                "vat_rate": 0,
                "is_generated": 1,
            }],
        })
        snap.flags.skip_immutability = True
        snap.flags.skip_generated_guard = True
        snap.insert()
        snap.submit()
        frappe.flags.allow_live_manual_lines = False
        return snap

    def test_addendum_requires_reason(self):
        """Addendum with empty reason must fail validate()."""
        snap = self._make_approved_snapshot_with_allowance()
        doc = frappe.get_doc({
            "doctype": "MPIT Budget Addendum",
            "year": self.YEAR,
            "cost_center": self.cc.name,
            "reference_snapshot": snap.name,
            "delta_amount": 100,
            "reason": "",  # empty
        })
        with self.assertRaises(frappe.ValidationError):
            doc.insert()

    def test_addendum_submit_requires_approved_snapshot(self):
        """Addendum cannot be submitted against a Draft (non-approved) Snapshot."""
        # Create a Draft snapshot (not submitted)
        frappe.flags.allow_live_manual_lines = True
        draft_snap = frappe.get_doc({
            "doctype": "MPIT Budget",
            "year": self.YEAR,
            "budget_type": "Snapshot",
            "title": f"DraftSnap {frappe.generate_hash(length=4)}",
            "workflow_state": "Draft",
            "lines": [{
                "doctype": "MPIT Budget Line",
                "cost_center": self.cc.name,
                "line_kind": "Allowance",
                "monthly_amount": 500,
                "recurrence_rule": "Monthly",
                "amount_includes_vat": 0,
                "vat_rate": 0,
                "is_generated": 1,
            }],
        })
        draft_snap.flags.skip_generated_guard = True
        draft_snap.flags.skip_immutability = True
        draft_snap.insert()
        frappe.flags.allow_live_manual_lines = False

        doc = frappe.get_doc({
            "doctype": "MPIT Budget Addendum",
            "year": self.YEAR,
            "cost_center": self.cc.name,
            "reference_snapshot": draft_snap.name,
            "delta_amount": 100,
            "reason": "Test addendum",
        })
        doc.insert()
        with self.assertRaises(frappe.ValidationError):
            doc.submit()

    def test_addendum_submit_requires_allowance_line_in_snapshot(self):
        """Addendum submit fails when the snapshot has no Allowance line for the cost center."""
        other_cc = _ensure_cost_center(f"ADD-OTHER-CC-{frappe.generate_hash(length=6)}")

        # Create approved snapshot with allowance for a DIFFERENT cost center
        frappe.flags.allow_live_manual_lines = True
        snap = frappe.get_doc({
            "doctype": "MPIT Budget",
            "year": self.YEAR,
            "budget_type": "Snapshot",
            "title": f"SnapNoAllowance {frappe.generate_hash(length=4)}",
            "workflow_state": "Draft",
            "lines": [{
                "doctype": "MPIT Budget Line",
                "cost_center": other_cc.name,  # Allowance for OTHER cc, not self.cc
                "line_kind": "Allowance",
                "monthly_amount": 500,
                "recurrence_rule": "Monthly",
                "amount_includes_vat": 0,
                "vat_rate": 0,
                "is_generated": 1,
            }],
        })
        snap.flags.skip_generated_guard = True
        snap.flags.skip_immutability = True
        snap.insert()
        snap.submit()
        frappe.flags.allow_live_manual_lines = False

        doc = frappe.get_doc({
            "doctype": "MPIT Budget Addendum",
            "year": self.YEAR,
            "cost_center": self.cc.name,  # self.cc has no Allowance line in snapshot
            "reference_snapshot": snap.name,
            "delta_amount": 100,
            "reason": "Addendum without allowance",
        })
        doc.insert()
        with self.assertRaises(frappe.ValidationError):
            doc.submit()

    def test_addendum_happy_path(self):
        """Valid Addendum inserts and submits without errors."""
        snap = self._make_approved_snapshot_with_allowance()
        doc = frappe.get_doc({
            "doctype": "MPIT Budget Addendum",
            "year": self.YEAR,
            "cost_center": self.cc.name,
            "reference_snapshot": snap.name,
            "delta_amount": 200,
            "reason": "Valid additional budget",
        })
        doc.insert()
        doc.submit()

        self.assertEqual(doc.docstatus, 1)
        self.assertIn("ADD-", doc.name)


# ---------------------------------------------------------------------------
# Section 7: Budget total_amount_monthly assertion
# ---------------------------------------------------------------------------

class TestBudgetTotalMonthly(FrappeTestCase):
    """Budget-level total_amount_monthly must equal total_amount_net / 12.

    Source: mpit_budget.py _compute_totals():
        self.total_amount_monthly = flt(total_net / 12.0, 2)
    """

    YEAR = "2046"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"BTM-CC-{frappe.generate_hash(length=6)}")

    def test_total_monthly_equals_net_over_12(self):
        """total_amount_monthly == total_amount_net / 12 for a monthly contract."""
        contract = _make_contract(
            f"BTM-Vendor-{frappe.generate_hash(length=6)}",
            self.cc.name,
            amount=1200,
            year=self.YEAR,
        )
        budget = _make_live_budget(self.YEAR)
        budget.refresh_from_sources()
        budget.reload()

        # Sanity: at least the contract line is present
        contract_lines = [ln for ln in budget.lines if ln.contract == contract.name]
        self.assertTrue(contract_lines, "Expected at least one budget line from the contract")

        # Core assertion
        net = flt(budget.total_amount_net)
        monthly = flt(budget.total_amount_monthly)
        self.assertAlmostEqual(
            monthly, net / 12.0, places=2,
            msg=f"total_amount_monthly ({monthly}) must equal total_amount_net ({net}) / 12"
        )

    def test_total_vat_consistency(self):
        """total_amount_gross must equal total_amount_net + total_amount_vat."""
        contract = _make_contract(
            f"BTM-VAT-Vendor-{frappe.generate_hash(length=6)}",
            self.cc.name,
            amount=1000,
            year=self.YEAR,
        )
        budget = _make_live_budget(self.YEAR)
        budget.refresh_from_sources()
        budget.reload()

        net = flt(budget.total_amount_net)
        vat = flt(budget.total_amount_vat)
        gross = flt(budget.total_amount_gross)

        self.assertAlmostEqual(
            gross, net + vat, places=2,
            msg="total_amount_gross must equal total_amount_net + total_amount_vat"
        )
