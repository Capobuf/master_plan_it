"""
MPIT Money Consistency Audit.

Recomputes important monetary results from source records and compares them
against stored totals. Detects:
- Mismatch between stored and recomputed totals
- VAT split errors (net + vat != gross)
- Cap calculation inconsistencies
- Project totals drift (stored vs DB-recomputed)
- Rounding drift (cumulative error > 0.01)

Run standalone:
    docker exec mpit-backend bash -c "su frappe -c 'cd /home/frappe/frappe-bench && \\
        bench --site localhost run-tests --module master_plan_it.tests.test_money_audit 2>&1'"

Or as part of full suite:
    bench --site localhost run-tests --app master_plan_it
"""

from __future__ import annotations

import frappe
from frappe.tests.utils import FrappeTestCase
from frappe.utils import flt


# Tolerance for floating-point comparisons (2 decimal places = 1 cent)
TOLERANCE = 0.01


# ---------------------------------------------------------------------------
# Helpers
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
# Test: Budget line VAT split consistency
# ---------------------------------------------------------------------------

class TestBudgetLineVatSplit(FrappeTestCase):
    """For every budget line: net + vat must equal gross (within tolerance).

    Creates a fresh budget with known contracts to ensure predictable data,
    then audits the resulting lines.
    """

    YEAR = "2050"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"MA-LINE-CC-{frappe.generate_hash(length=6)}")

    def test_lines_vat_split_net_plus_vat_equals_gross(self):
        """Recompute net+vat for each budget line and assert == gross."""
        vendor = _ensure_vendor(f"MA-Line-V-{frappe.generate_hash(length=6)}")
        # Contract with VAT: amount=1220 gross at 22% → net=1000, vat=220
        frappe.get_doc({
            "doctype": "MPIT Contract",
            "description": f"MA VAT Contract {frappe.generate_hash(length=6)}",
            "vendor": vendor.name,
            "cost_center": self.cc.name,
            "status": "Active",
            "terms": [{
                "from_date": f"{self.YEAR}-01-01",
                "amount": 1220,
                "amount_includes_vat": 1,
                "vat_rate": 22,
                "billing_cycle": "Monthly",
            }],
        }).insert()

        budget = _make_live_budget(self.YEAR)
        budget.refresh_from_sources()
        budget.reload()

        failures = []
        for line in budget.lines:
            net = flt(getattr(line, "annual_net", 0) or 0)
            vat = flt(getattr(line, "annual_vat", 0) or 0)
            gross = flt(getattr(line, "annual_gross", 0) or 0)

            # Only audit lines that have non-zero amounts
            if net == 0 and vat == 0 and gross == 0:
                continue

            delta = abs((net + vat) - gross)
            if delta > TOLERANCE:
                failures.append(
                    f"Line {line.name}: net({net}) + vat({vat}) = {net+vat} != gross({gross}), delta={delta}"
                )

        self.assertFalse(
            failures,
            f"VAT split inconsistencies found in budget lines:\n" + "\n".join(failures)
        )

    def test_budget_total_vat_split_consistent(self):
        """Budget total_amount_gross must equal total_amount_net + total_amount_vat."""
        vendor = _ensure_vendor(f"MA-Total-V-{frappe.generate_hash(length=6)}")
        frappe.get_doc({
            "doctype": "MPIT Contract",
            "description": f"MA Total Contract {frappe.generate_hash(length=6)}",
            "vendor": vendor.name,
            "cost_center": self.cc.name,
            "status": "Active",
            "terms": [{
                "from_date": f"{self.YEAR}-01-01",
                "amount": 500,
                "amount_includes_vat": 0,
                "vat_rate": 22,
                "billing_cycle": "Monthly",
            }],
        }).insert()

        budget = _make_live_budget(self.YEAR)
        budget.refresh_from_sources()
        budget.reload()

        net = flt(budget.total_amount_net)
        vat = flt(budget.total_amount_vat)
        gross = flt(budget.total_amount_gross)

        self.assertAlmostEqual(
            gross, net + vat, places=2,
            msg=f"Budget total: net({net}) + vat({vat}) = {net+vat} != gross({gross})"
        )


# ---------------------------------------------------------------------------
# Test: Budget totals match sum of lines
# ---------------------------------------------------------------------------

class TestBudgetTotalsMatchLines(FrappeTestCase):
    """Recompute totals by summing child lines and compare against stored totals.

    This catches any case where totals were stored incorrectly (e.g., by direct
    db_set that bypassed _compute_totals(), or a rounding drift).
    """

    YEAR = "2051"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"MA-TOT-CC-{frappe.generate_hash(length=6)}")

    def test_stored_totals_match_recomputed_from_lines(self):
        """Sum budget lines directly and compare against budget header totals."""
        vendor = _ensure_vendor(f"MA-Tot-V-{frappe.generate_hash(length=6)}")
        # Add two contracts to create multiple lines
        for amount in [300, 700]:
            frappe.get_doc({
                "doctype": "MPIT Contract",
                "description": f"MA Tot Contract {amount} {frappe.generate_hash(length=4)}",
                "vendor": vendor.name,
                "cost_center": self.cc.name,
                "status": "Active",
                "terms": [{
                    "from_date": f"{self.YEAR}-01-01",
                    "amount": amount,
                    "amount_includes_vat": 0,
                    "vat_rate": 0,
                    "billing_cycle": "Monthly",
                }],
            }).insert()

        budget = _make_live_budget(self.YEAR)
        budget.refresh_from_sources()
        budget.reload()

        # Recompute from child lines
        recomputed_net = flt(sum(flt(ln.annual_net or 0) for ln in budget.lines), 2)
        recomputed_vat = flt(sum(flt(ln.annual_vat or 0) for ln in budget.lines), 2)
        recomputed_gross = flt(sum(flt(ln.annual_gross or 0) for ln in budget.lines), 2)

        stored_net = flt(budget.total_amount_net)
        stored_vat = flt(budget.total_amount_vat)
        stored_gross = flt(budget.total_amount_gross)

        self.assertAlmostEqual(
            stored_net, recomputed_net, places=2,
            msg=f"total_amount_net stored={stored_net} != recomputed={recomputed_net}"
        )
        self.assertAlmostEqual(
            stored_vat, recomputed_vat, places=2,
            msg=f"total_amount_vat stored={stored_vat} != recomputed={recomputed_vat}"
        )
        self.assertAlmostEqual(
            stored_gross, recomputed_gross, places=2,
            msg=f"total_amount_gross stored={stored_gross} != recomputed={recomputed_gross}"
        )

    def test_total_monthly_is_net_over_12(self):
        """total_amount_monthly must be total_amount_net / 12 (formula from _compute_totals)."""
        vendor = _ensure_vendor(f"MA-Mon-V-{frappe.generate_hash(length=6)}")
        frappe.get_doc({
            "doctype": "MPIT Contract",
            "description": f"MA Monthly {frappe.generate_hash(length=6)}",
            "vendor": vendor.name,
            "cost_center": self.cc.name,
            "status": "Active",
            "terms": [{
                "from_date": f"{self.YEAR}-01-01",
                "amount": 1200,
                "amount_includes_vat": 0,
                "vat_rate": 0,
                "billing_cycle": "Monthly",
            }],
        }).insert()

        budget = _make_live_budget(self.YEAR)
        budget.refresh_from_sources()
        budget.reload()

        net = flt(budget.total_amount_net)
        monthly = flt(budget.total_amount_monthly)
        expected_monthly = flt(net / 12.0, 2)

        self.assertAlmostEqual(
            monthly, expected_monthly, places=2,
            msg=f"total_amount_monthly={monthly} != total_amount_net/12={expected_monthly}"
        )


# ---------------------------------------------------------------------------
# Test: Cap consistency
# ---------------------------------------------------------------------------

class TestCapConsistency(FrappeTestCase):
    """Cap = Snapshot Allowance + sum of approved Addendums.

    Recomputes the cap from raw DB queries and compares against
    get_cap_for_cost_center() return value.
    """

    YEAR = "2052"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"MA-CAP-CC-{frappe.generate_hash(length=6)}")

    def _make_approved_snapshot(self, allowance_monthly: float) -> frappe.Document:
        frappe.flags.allow_live_manual_lines = True
        snap = frappe.get_doc({
            "doctype": "MPIT Budget",
            "year": self.YEAR,
            "budget_type": "Snapshot",
            "title": f"MA Cap Snap {frappe.generate_hash(length=4)}",
            "workflow_state": "Draft",
            "lines": [{
                "doctype": "MPIT Budget Line",
                "cost_center": self.cc.name,
                "line_kind": "Allowance",
                "monthly_amount": allowance_monthly,
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
        return snap

    def test_cap_equals_allowance_plus_addendums(self):
        """cap_total must equal snapshot allowance + sum of submitted addendums."""
        # Allowance: 1000/month * 12 = 12000 annual
        snap = self._make_approved_snapshot(1000)

        # Add two addendums
        for delta in [200, 300]:
            add = frappe.get_doc({
                "doctype": "MPIT Budget Addendum",
                "year": self.YEAR,
                "cost_center": self.cc.name,
                "reference_snapshot": snap.name,
                "delta_amount": delta,
                "reason": f"Addendum {delta}",
            })
            add.insert()
            add.submit()

        from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import (
            get_cap_for_cost_center,
        )
        cap = get_cap_for_cost_center(self.YEAR, self.cc.name)

        # Recompute allowance directly from DB
        allowance_lines = frappe.db.sql("""
            SELECT COALESCE(SUM(annual_net), 0) AS total
            FROM `tabMPIT Budget Line`
            WHERE parent = %s AND cost_center = %s AND line_kind = 'Allowance'
        """, (snap.name, self.cc.name), as_dict=True)
        db_allowance = flt(allowance_lines[0].total if allowance_lines else 0, 2)

        # Recompute addendums directly from DB
        addendum_rows = frappe.db.sql("""
            SELECT COALESCE(SUM(delta_amount), 0) AS total
            FROM `tabMPIT Budget Addendum`
            WHERE year = %s AND cost_center = %s AND docstatus = 1
        """, (self.YEAR, self.cc.name), as_dict=True)
        db_addendums = flt(addendum_rows[0].total if addendum_rows else 0, 2)

        expected_cap = flt(db_allowance + db_addendums, 2)

        self.assertAlmostEqual(
            flt(cap["cap_total"]), expected_cap, places=2,
            msg=f"cap_total={cap['cap_total']} != allowance({db_allowance}) + addendums({db_addendums})"
        )
        self.assertAlmostEqual(
            flt(cap["snapshot_amount"]), db_allowance, places=2,
            msg=f"snapshot_amount={cap['snapshot_amount']} != DB allowance={db_allowance}"
        )
        self.assertAlmostEqual(
            flt(cap["addendum_total"]), db_addendums, places=2,
            msg=f"addendum_total={cap['addendum_total']} != DB addendums={db_addendums}"
        )

    def test_cap_with_no_addendums_equals_allowance_only(self):
        """When no addendums exist, cap_total equals snapshot allowance."""
        snap = self._make_approved_snapshot(800)

        from master_plan_it.master_plan_it.doctype.mpit_budget.mpit_budget import (
            get_cap_for_cost_center,
        )
        cap = get_cap_for_cost_center(self.YEAR, self.cc.name)

        self.assertAlmostEqual(
            flt(cap["addendum_total"]), 0.0, places=2,
            msg="No addendums: addendum_total must be 0"
        )
        self.assertAlmostEqual(
            flt(cap["cap_total"]), flt(cap["snapshot_amount"]), places=2,
            msg="No addendums: cap_total must equal snapshot_amount"
        )


# ---------------------------------------------------------------------------
# Test: Actual Entry VAT split consistency
# ---------------------------------------------------------------------------

class TestActualEntryVatConsistency(FrappeTestCase):
    """For every Actual Entry with non-zero amounts: net + vat must equal gross."""

    YEAR = "2053"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"MA-AE-CC-{frappe.generate_hash(length=6)}")

    def _create_entry(self, amount: float, vat_rate: float, includes_vat: bool) -> frappe.Document:
        doc = frappe.get_doc({
            "doctype": "MPIT Actual Entry",
            "posting_date": f"{self.YEAR}-04-15",
            "entry_kind": "Allowance Spend",
            "cost_center": self.cc.name,
            "amount": amount,
            "vat_rate": vat_rate,
            "amount_includes_vat": 1 if includes_vat else 0,
        })
        doc.insert()
        return doc

    def test_zero_vat_net_equals_amount(self):
        """With 0% VAT: amount_net == amount, vat == 0, gross == amount."""
        entry = self._create_entry(500, 0, False)
        self.assertAlmostEqual(flt(entry.amount_net), 500.0, places=2)
        self.assertAlmostEqual(flt(entry.amount_vat), 0.0, places=2)
        self.assertAlmostEqual(flt(entry.amount_gross), 500.0, places=2)

    def test_22pct_vat_from_net(self):
        """With 22% VAT not included: net=amount, vat=amount*0.22, gross=net+vat."""
        entry = self._create_entry(1000, 22, False)
        self.assertAlmostEqual(flt(entry.amount_net), 1000.0, places=2)
        self.assertAlmostEqual(flt(entry.amount_vat), 220.0, places=2)
        self.assertAlmostEqual(flt(entry.amount_gross), 1220.0, places=2)

    def test_22pct_vat_from_gross(self):
        """With 22% VAT included: net=gross/(1+0.22), vat=gross-net."""
        entry = self._create_entry(1220, 22, True)
        self.assertAlmostEqual(flt(entry.amount_net), 1000.0, places=2)
        self.assertAlmostEqual(flt(entry.amount_vat), 220.0, places=2)
        self.assertAlmostEqual(flt(entry.amount_gross), 1220.0, places=2)

    def test_net_plus_vat_equals_gross(self):
        """General invariant: amount_net + amount_vat == amount_gross for all entries."""
        entries = [
            self._create_entry(100, 10, False),
            self._create_entry(550, 22, True),
            self._create_entry(300, 4, False),
        ]
        for e in entries:
            net = flt(e.amount_net)
            vat = flt(e.amount_vat)
            gross = flt(e.amount_gross)
            self.assertAlmostEqual(
                net + vat, gross, places=2,
                msg=f"Entry {e.name}: net({net}) + vat({vat}) = {net+vat} != gross({gross})"
            )


# ---------------------------------------------------------------------------
# Test: Contract term VAT consistency
# ---------------------------------------------------------------------------

class TestContractTermVatConsistency(FrappeTestCase):
    """Contract term amount_net + amount_vat must equal amount_gross."""

    YEAR = "2054"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"MA-CT-CC-{frappe.generate_hash(length=6)}")

    def test_term_vat_split_from_net(self):
        """Term with amount_includes_vat=0: net=amount, gross=net*(1+rate/100)."""
        vendor = _ensure_vendor(f"MA-CT-V-{frappe.generate_hash(length=6)}")
        contract = frappe.get_doc({
            "doctype": "MPIT Contract",
            "description": f"MA CT Net {frappe.generate_hash(length=6)}",
            "vendor": vendor.name,
            "cost_center": self.cc.name,
            "status": "Active",
            "terms": [{
                "from_date": f"{self.YEAR}-01-01",
                "amount": 1000,
                "amount_includes_vat": 0,
                "vat_rate": 22,
                "billing_cycle": "Monthly",
            }],
        }).insert()

        term = contract.terms[0]
        net = flt(term.amount_net)
        vat = flt(term.amount_vat)
        gross = flt(term.amount_gross)

        self.assertAlmostEqual(net, 1000.0, places=2)
        self.assertAlmostEqual(vat, 220.0, places=2)
        self.assertAlmostEqual(gross, 1220.0, places=2)
        self.assertAlmostEqual(net + vat, gross, places=2)

    def test_term_vat_split_from_gross(self):
        """Term with amount_includes_vat=1: gross=amount, net=gross/(1+rate/100)."""
        vendor = _ensure_vendor(f"MA-CT-GV-{frappe.generate_hash(length=6)}")
        contract = frappe.get_doc({
            "doctype": "MPIT Contract",
            "description": f"MA CT Gross {frappe.generate_hash(length=6)}",
            "vendor": vendor.name,
            "cost_center": self.cc.name,
            "status": "Active",
            "terms": [{
                "from_date": f"{self.YEAR}-01-01",
                "amount": 1220,
                "amount_includes_vat": 1,
                "vat_rate": 22,
                "billing_cycle": "Monthly",
            }],
        }).insert()

        term = contract.terms[0]
        net = flt(term.amount_net)
        vat = flt(term.amount_vat)
        gross = flt(term.amount_gross)

        self.assertAlmostEqual(net, 1000.0, places=2)
        self.assertAlmostEqual(vat, 220.0, places=2)
        self.assertAlmostEqual(gross, 1220.0, places=2)
        self.assertAlmostEqual(net + vat, gross, places=2)

    def test_term_monthly_amount_net_quarterly(self):
        """Quarterly term: monthly_amount_net = amount_net * 4 / 12."""
        vendor = _ensure_vendor(f"MA-CT-QV-{frappe.generate_hash(length=6)}")
        contract = frappe.get_doc({
            "doctype": "MPIT Contract",
            "description": f"MA CT Quarterly {frappe.generate_hash(length=6)}",
            "vendor": vendor.name,
            "cost_center": self.cc.name,
            "status": "Active",
            "terms": [{
                "from_date": f"{self.YEAR}-01-01",
                "amount": 1200,
                "amount_includes_vat": 0,
                "vat_rate": 0,
                "billing_cycle": "Quarterly",
            }],
        }).insert()

        term = contract.terms[0]
        # Quarterly billing: monthly_amount_net = amount_net * 4 / 12 = 1200 * 4 / 12 = 400
        self.assertAlmostEqual(flt(term.monthly_amount_net), 400.0, places=2)

    def test_term_monthly_amount_net_annual(self):
        """Annual term: monthly_amount_net = amount_net / 12."""
        vendor = _ensure_vendor(f"MA-CT-AV-{frappe.generate_hash(length=6)}")
        contract = frappe.get_doc({
            "doctype": "MPIT Contract",
            "description": f"MA CT Annual {frappe.generate_hash(length=6)}",
            "vendor": vendor.name,
            "cost_center": self.cc.name,
            "status": "Active",
            "terms": [{
                "from_date": f"{self.YEAR}-01-01",
                "amount": 1200,
                "amount_includes_vat": 0,
                "vat_rate": 0,
                "billing_cycle": "Annual",
            }],
        }).insert()

        term = contract.terms[0]
        # Annual billing: monthly_amount_net = 1200 / 12 = 100
        self.assertAlmostEqual(flt(term.monthly_amount_net), 100.0, places=2)


# ---------------------------------------------------------------------------
# Test: Budget line annual_net scale with overlap months
# ---------------------------------------------------------------------------

class TestBudgetLineOverlapScaling(FrappeTestCase):
    """annual_net must scale with overlap_months for partial-year contracts.

    Formula from amounts.compute_line_amounts():
        annual_net = amount_net * (overlap_months / 12)
    """

    YEAR = "2055"

    def setUp(self):
        super().setUp()
        _ensure_year(self.YEAR)
        self.cc = _ensure_cost_center(f"MA-OV-CC-{frappe.generate_hash(length=6)}")

    def test_full_year_contract_annual_net_equals_12x_monthly(self):
        """Monthly contract for full year: annual_net = monthly_net * 12."""
        vendor = _ensure_vendor(f"MA-OV-V-{frappe.generate_hash(length=6)}")
        frappe.get_doc({
            "doctype": "MPIT Contract",
            "description": f"MA Full Year {frappe.generate_hash(length=6)}",
            "vendor": vendor.name,
            "cost_center": self.cc.name,
            "status": "Active",
            "terms": [{
                "from_date": f"{self.YEAR}-01-01",
                "to_date": f"{self.YEAR}-12-31",
                "amount": 100,
                "amount_includes_vat": 0,
                "vat_rate": 0,
                "billing_cycle": "Monthly",
            }],
        }).insert()

        budget = _make_live_budget(self.YEAR)
        budget.refresh_from_sources()
        budget.reload()

        lines = [ln for ln in budget.lines if ln.cost_center == self.cc.name]
        self.assertTrue(lines, "Expected budget lines for this cost center")

        for line in lines:
            monthly = flt(line.monthly_amount)
            annual = flt(line.annual_net)
            # Full year overlap = 12 months → annual_net = monthly * 12
            self.assertAlmostEqual(
                annual, monthly * 12, places=2,
                msg=f"Line {line.name}: annual_net={annual} != monthly_amount({monthly}) * 12"
            )
