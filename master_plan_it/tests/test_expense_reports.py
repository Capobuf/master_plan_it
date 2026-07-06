from __future__ import annotations

import os
import re
from uuid import uuid4

import frappe
from frappe.tests.utils import FrappeTestCase

from master_plan_it.master_plan_it.dashboard_chart_source.mpit_plafond_usage_by_cost_center.mpit_plafond_usage_by_cost_center import (
    get_data as run_plafond_usage_by_cost_center_chart,
)
from master_plan_it.master_plan_it.report.mpit_expenses.mpit_expenses import execute as run_expenses
from master_plan_it.master_plan_it.report.mpit_monthly_plan.mpit_monthly_plan import execute as run_monthly
from master_plan_it.master_plan_it.report.mpit_overview.mpit_overview import execute as run_overview
from master_plan_it.master_plan_it.report.mpit_project_forecast_vs_actual.mpit_project_forecast_vs_actual import (
    execute as run_project_report,
)


class TestExpenseReports(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.suffix = uuid4().hex[:6].upper()
        self.year = ensure_year(2031)
        self.cost_center = ensure_cost_center(f"CC-REP-{self.suffix}")
        self.project = ensure_project(f"Project REP {self.suffix}", self.cost_center)
        self.vendor = ensure_vendor(f"Vendor REP {self.suffix}")

        expense = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Expense REP {self.suffix}",
                "expense_kind": "Ordinary",
                "year": self.year,
                "cost_center": self.cost_center,
                "project": self.project,
                "uses_plafond": 0,
                "is_extra": 1,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Estimate",
                        "row_phase": "Estimate",
                        "row_state": "Active",
                        "vendor": self.vendor,
                        "amount": 120,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "spend_date": "2031-01-10",
                    },
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Actual",
                        "row_phase": "Actual",
                        "row_state": "Active",
                        "vendor": self.vendor,
                        "amount": 50,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "spend_date": "2031-02-10",
                    },
                ],
            }
        )
        expense.insert()

    def test_overview_report_runs_on_expense_engine(self):
        columns, data, *_rest = run_overview({"year": self.year, "cost_center": self.cost_center})
        self.assertTrue(columns)
        self.assertTrue(data)
        self.assertIn("forecast_total", data[0])
        self.assertIn("actual_total", data[0])

    def test_monthly_plan_report_runs_on_expense_engine(self):
        columns, data, *_rest = run_monthly({"year": self.year, "project": self.project})
        self.assertTrue(columns)
        self.assertEqual(len(data), 12)
        self.assertIn("forecast", data[0])
        self.assertIn("actual", data[0])

    def test_expenses_report_runs(self):
        columns, data = run_expenses({"year": self.year, "cost_center": self.cost_center})
        self.assertTrue(columns)
        self.assertTrue(data)
        self.assertEqual(data[0]["year"], self.year)

    def test_expenses_report_shows_calculated_plafond_totals(self):
        plafond = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Plafond REP {self.suffix}",
                "expense_kind": "Plafond",
                "year": self.year,
                "cost_center": self.cost_center,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Plafond allocation",
                        "row_state": "Active",
                        "amount": 1000,
                        "amount_includes_vat": 0,
                        "vat_rate": 0,
                        "spend_date": "2031-01-10",
                    }
                ],
            }
        ).insert()

        frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Plafond Consumer REP {self.suffix}",
                "expense_kind": "Ordinary",
                "year": self.year,
                "cost_center": self.cost_center,
                "project": self.project,
                "uses_plafond": 1,
                "is_extra": 0,
                "plafond_expense": plafond.name,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Plafond actual",
                        "row_phase": "Actual",
                        "row_state": "Active",
                        "vendor": self.vendor,
                        "amount": 250,
                        "amount_includes_vat": 0,
                        "vat_rate": 0,
                        "spend_date": "2031-02-10",
                    }
                ],
            }
        ).insert()

        columns, data = run_expenses({"year": self.year, "cost_center": self.cost_center})
        fieldnames = {column["fieldname"] for column in columns}
        for expected in {"plafond", "plafond_consumed", "plafond_remaining", "plafond_over"}:
            self.assertIn(expected, fieldnames)

        target = next((row for row in data if row.get("expense") == plafond.name), None)
        self.assertIsNotNone(target)
        self.assertEqual(target.get("plafond"), 1000)
        self.assertEqual(target.get("plafond_consumed"), 250)
        self.assertEqual(target.get("plafond_remaining"), 750)
        self.assertEqual(target.get("plafond_over"), 0)

    def test_expenses_report_shows_standard_funding_for_standard_ordinary(self):
        standard = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Expense REP Standard {self.suffix}",
                "expense_kind": "Ordinary",
                "year": self.year,
                "cost_center": self.cost_center,
                "project": self.project,
                "uses_plafond": 0,
                "is_extra": 0,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Actual Standard",
                        "row_phase": "Actual",
                        "row_state": "Active",
                        "vendor": self.vendor,
                        "amount": 40,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "spend_date": "2031-03-10",
                    },
                ],
            }
        ).insert()

        _columns, data = run_expenses({"year": self.year, "cost_center": self.cost_center})
        target = next((row for row in data if row.get("expense") == standard.name), None)
        self.assertIsNotNone(target)
        self.assertEqual(target.get("funding"), "Standard")

    def test_project_forecast_vs_actual_report_runs(self):
        columns, data = run_project_report({"year": self.year, "cost_center": self.cost_center})
        self.assertTrue(columns)
        self.assertTrue(data)
        self.assertIn("variance", data[0])


class TestOverviewModes(FrappeTestCase):
    """Tests for the three MPIT Overview view modes: Summary, Build-up, Lines."""

    @classmethod
    def setUpClass(cls):
        super().setUpClass()
        frappe.set_user("Administrator")
        cls.suffix = uuid4().hex[:6].upper()
        cls.year = ensure_year(2032)
        cls.cc = ensure_cost_center(f"CC-OVW-{cls.suffix}")
        cls.cc2 = ensure_cost_center(f"CC-OVW2-{cls.suffix}")
        cls.project = ensure_project(f"OVW Project {cls.suffix}", cls.cc)

        cls.vendor_name = ensure_vendor(f"Vendor OVW {cls.suffix}")
        cls.contract_context = _insert_contract(
            f"CTR-CONTEXT-{cls.suffix}",
            cls.cc,
            cls.vendor_name,
            terms=[
                {
                    "from_date": "2032-01-01",
                    "to_date": "2032-12-31",
                    "amount": 350.0,
                    "amount_net": 350.0,
                    "billing_cycle": "Monthly",
                }
            ],
        )

        # Contract WITH terms
        cls.contract_with_terms = _insert_contract(
            f"CTR-WT-{cls.suffix}",
            cls.cc2,
            cls.vendor_name,
            start_date="2032-01-01",
            end_date="2032-12-31",
            terms=[
                {
                    "from_date": "2032-01-01",
                    "to_date": "2032-06-30",
                    "amount": 400.0,
                    "amount_net": 400.0,
                    "billing_cycle": "Monthly",
                },
                {
                    "from_date": "2032-07-01",
                    "to_date": None,
                    "amount": 500.0,
                    "amount_net": 500.0,
                    "billing_cycle": "Monthly",
                },
            ],
        )

        # Expense with Estimate + Quote + Actual rows (on plafond)
        cls.plafond_expense = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Plafond OVW {cls.suffix}",
                "expense_kind": "Plafond",
                "year": cls.year,
                "cost_center": cls.cc,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Budget",
                        "row_state": "Active",
                        "amount": 5000.0,
                        "amount_includes_vat": 0,
                        "vat_rate": 0,
                        "spend_date": "2032-01-01",
                    },
                ],
            }
        )
        cls.plafond_expense.insert()

        cls.ordinary_expense = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Expense OVW {cls.suffix}",
                "expense_kind": "Ordinary",
                "year": cls.year,
                "cost_center": cls.cc,
                "project": cls.project,
                "uses_plafond": 1,
                "plafond_expense": cls.plafond_expense.name,
                "is_extra": 0,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Estimate row",
                        "row_phase": "Estimate",
                        "row_state": "Active",
                        "vendor": cls.vendor_name,
                        "amount": 300.0,
                        "amount_includes_vat": 0,
                        "vat_rate": 0,
                        "spend_date": "2032-03-01",
                    },
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Quote row",
                        "row_phase": "Quote",
                        "row_state": "Active",
                        "vendor": cls.vendor_name,
                        "amount": 280.0,
                        "amount_includes_vat": 0,
                        "vat_rate": 0,
                        "spend_date": "2032-04-01",
                    },
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Actual row",
                        "row_phase": "Actual",
                        "row_state": "Active",
                        "vendor": cls.vendor_name,
                        "amount": 290.0,
                        "amount_includes_vat": 0,
                        "vat_rate": 0,
                        "spend_date": "2032-05-01",
                    },
                ],
            }
        )
        cls.ordinary_expense.insert()

        cls.standard_expense = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Expense Standard OVW {cls.suffix}",
                "expense_kind": "Ordinary",
                "year": cls.year,
                "cost_center": cls.cc,
                "project": cls.project,
                "uses_plafond": 0,
                "is_extra": 0,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Standard actual row",
                        "row_phase": "Actual",
                        "row_state": "Active",
                        "vendor": cls.vendor_name,
                        "amount": 110.0,
                        "amount_includes_vat": 0,
                        "vat_rate": 0,
                        "spend_date": "2032-06-01",
                    },
                ],
            }
        )
        cls.standard_expense.insert()

    # ── Summary mode ──────────────────────────────────────────────────────

    def test_summary_returns_canonical_fields(self):
        """Summary mode returns all canonical budget-like columns."""
        columns, data, *_ = run_overview(
            {"year": self.year, "cost_center": self.cc, "view_mode": "Summary"}
        )
        fieldnames = {c["fieldname"] for c in columns}
        for expected in [
            "cost_center", "forecast_estimate", "forecast_quote",
            "forecast_total", "actual_standard", "actual_on_plafond", "actual_extra", "actual_total",
            "approved_budget", "proposals", "ideas", "plafond", "remaining", "over",
        ]:
            self.assertIn(expected, fieldnames, f"Missing column: {expected}")

    def test_summary_has_data_row(self):
        """Summary mode returns at least one data row for the test CC."""
        columns, data, *_ = run_overview(
            {"year": self.year, "cost_center": self.cc, "view_mode": "Summary"}
        )
        self.assertTrue(data, "Expected at least one summary row")
        self.assertIn("forecast_total", data[0])
        self.assertIn("actual_standard", data[0])
        self.assertIn("actual_total", data[0])

    def test_summary_report_summary_exposes_actual_standard(self):
        columns, data, _message, _chart, report_summary = run_overview(
            {"year": self.year, "cost_center": self.cc, "view_mode": "Summary"}
        )
        self.assertTrue(columns)
        self.assertTrue(data)
        labels = {row.get("label") for row in report_summary or []}
        self.assertIn("Ordinary Actual Spend", labels)

    def test_summary_report_summary_uses_business_labels(self):
        columns, data, _message, _chart, report_summary = run_overview(
            {"year": self.year, "cost_center": self.cc, "view_mode": "Summary"}
        )
        self.assertTrue(columns)
        self.assertTrue(data)

        labels = {row.get("label") for row in report_summary or []}
        for expected in {
            "Forecast Budget",
            "Actual Spend",
            "Approved Projects",
            "Proposed Projects",
            "Ideas Outside Budget",
            "Ordinary Actual Spend",
            "Consumed Plafond",
            "Remaining Plafond",
            "Over Plafond",
            "Extra Budget",
        }:
            self.assertIn(expected, labels)

    def test_summary_project_filter_not_present_in_simple_call(self):
        """
        Summary mode: passing a project filter should not crash; the result
        is still a valid summary.
        """
        columns, data, *_ = run_overview(
            {
                "year": self.year,
                "cost_center": self.cc,
                "view_mode": "Summary",
                "project": self.project,
            }
        )
        self.assertIsInstance(data, list)

    # ── Build-up mode ─────────────────────────────────────────────────────

    def test_buildup_returns_hierarchical_rows(self):
        """Build-up produces header rows (indent=0) and block rows (indent=1)."""
        columns, data, *_ = run_overview(
            {"year": self.year, "cost_center": self.cc, "view_mode": "Build-up"}
        )
        self.assertTrue(data, "Expected build-up rows")
        indents = {r.get("indent", 0) for r in data}
        self.assertIn(0, indents, "Expected header rows at indent=0")
        self.assertIn(1, indents, "Expected block rows at indent=1")

    def test_buildup_has_summary_columns(self):
        """Build-up shares summary monetary columns."""
        columns, data, *_ = run_overview(
            {"year": self.year, "cost_center": self.cc, "view_mode": "Build-up"}
        )
        fieldnames = {c["fieldname"] for c in columns}
        for expected in ["forecast_total", "approved_budget", "proposals", "ideas", "actual_total", "plafond"]:
            self.assertIn(expected, fieldnames)

    def test_buildup_reconciles_blocks_to_header(self):
        """Sum of proposals block reconciles with header proposals."""
        columns, data, *_ = run_overview(
            {
                "year": self.year,
                "cost_center": self.cc,
                "view_mode": "Build-up",
                "section_scope": "All",
                "show_zero_rows": 1,
            }
        )
        # Find header row and its "Proposals" sub-block
        header = next((r for r in data if r.get("indent", 0) == 0 and r.get("cost_center") == self.cc), None)
        proposals_block = next(
            (r for r in data if r.get("indent", 0) == 1 and r.get("cost_center") == "Proposals"), None
        )
        if header and proposals_block:
            self.assertAlmostEqual(
                header.get("proposals", 0),
                proposals_block.get("proposals", 0),
                places=2,
            )

    def test_buildup_includes_actual_standard_block_and_total_invariant(self):
        columns, data, *_ = run_overview(
            {
                "year": self.year,
                "cost_center": self.cc,
                "view_mode": "Build-up",
                "section_scope": "All",
                "show_zero_rows": 1,
            }
        )
        self.assertTrue(columns)
        self.assertTrue(data)

        header = next((r for r in data if r.get("indent", 0) == 0 and r.get("cost_center") == self.cc), None)
        self.assertIsNotNone(header)

        standard_block = next(
            (r for r in data if r.get("indent", 0) == 1 and r.get("cost_center") == "Actual / Standard"),
            None,
        )
        self.assertIsNotNone(standard_block)
        self.assertGreater(standard_block.get("actual_standard", 0), 0)

        self.assertAlmostEqual(
            header.get("actual_total", 0),
            (header.get("actual_standard", 0) or 0)
            + (header.get("actual_on_plafond", 0) or 0)
            + (header.get("actual_extra", 0) or 0),
            places=2,
        )

    def test_buildup_project_filter_applies(self):
        """
        Build-up project filter must narrow expense totals.
        """
        cols_all, data_all, *_ = run_overview(
            {"year": self.year, "cost_center": self.cc, "view_mode": "Build-up"}
        )
        cols_proj, data_proj, *_ = run_overview(
            {
                "year": self.year,
                "cost_center": self.cc,
                "view_mode": "Build-up",
                "project": self.project,
            }
        )

        def header_forecast_total(rows):
            for r in rows:
                if r.get("indent", 0) == 0 and r.get("cost_center") == self.cc:
                    return r.get("forecast_total", 0)
            return 0

        self.assertGreaterEqual(header_forecast_total(data_all), header_forecast_total(data_proj))

    # ── Lines mode ────────────────────────────────────────────────────────

    def test_lines_returns_correct_columns(self):
        """Lines mode returns source-level columns."""
        columns, data, *_ = run_overview(
            {"year": self.year, "cost_center": self.cc, "view_mode": "Lines"}
        )
        fieldnames = {c["fieldname"] for c in columns}
        for expected in [
            "source_type", "source_document", "contract", "project", "project_bucket", "vendor",
            "expense_phase", "funding", "amount_net", "annual_contribution_net",
        ]:
            self.assertIn(expected, fieldnames)

    def test_lines_contains_expense_rows(self):
        """Lines mode produces Expense Row lines when ordinary expenses exist."""
        columns, data, *_ = run_overview(
            {"year": self.year, "cost_center": self.cc, "view_mode": "Lines"}
        )
        types = {r.get("source_type") for r in data}
        self.assertIn("Expense Row", types)

    def test_lines_expense_phases_present(self):
        """All three phases (Estimate, Quote, Actual) appear in Lines output."""
        columns, data, *_ = run_overview(
            {"year": self.year, "cost_center": self.cc, "view_mode": "Lines"}
        )
        phases = {r.get("expense_phase") for r in data if r.get("source_type") == "Expense Row"}
        self.assertIn("Estimate", phases)
        self.assertIn("Quote", phases)
        self.assertIn("Actual", phases)

    def test_lines_standard_expense_funding_label(self):
        _columns, data, *_ = run_overview(
            {"year": self.year, "cost_center": self.cc, "view_mode": "Lines", "section_scope": "Expenses"}
        )
        standard_line = next(
            (
                row for row in data
                if row.get("source_type") == "Expense Row"
                and row.get("source_document") == self.standard_expense.name
                and row.get("expense_phase") == "Actual"
            ),
            None,
        )
        self.assertIsNotNone(standard_line)
        self.assertEqual(standard_line.get("funding"), "Standard")

    def test_lines_does_not_emit_contract_term_source_type(self):
        columns, data, *_ = run_overview({"year": self.year, "cost_center": self.cc, "view_mode": "Lines"})
        self.assertTrue(columns)
        self.assertNotIn("Contract Term", {row.get("source_type") for row in data})

    def test_lines_plafond_rows_visible(self):
        """Lines mode shows Plafond lines when section_scope includes Plafond."""
        columns, data, *_ = run_overview(
            {
                "year": self.year,
                "cost_center": self.cc,
                "view_mode": "Lines",
                "section_scope": "Plafond",
            }
        )
        types = {r.get("source_type") for r in data}
        self.assertIn("Plafond", types)

    def test_lines_expense_phase_filter(self):
        """expense_phase filter restricts Lines output to that phase only."""
        columns, data, *_ = run_overview(
            {
                "year": self.year,
                "cost_center": self.cc,
                "view_mode": "Lines",
                "section_scope": "Expenses",
                "expense_phase": "Estimate",
            }
        )
        for row in data:
            if row.get("source_type") == "Expense Row":
                self.assertEqual(row.get("expense_phase"), "Estimate")

    def test_lines_project_filter_applies_to_effective_project(self):
        columns_proj, data_proj, *_ = run_overview(
            {
                "year": self.year,
                "cost_center": self.cc,
                "view_mode": "Lines",
                "section_scope": "Expenses",
                "project": self.project,
            }
        )
        self.assertTrue(columns_proj)
        self.assertTrue(data_proj)
        for row in data_proj:
            if row.get("source_type") == "Expense Row":
                self.assertEqual(row.get("project"), self.project)

    # ── Print infrastructure ──────────────────────────────────────────────

    def test_html_print_template_exists(self):
        """The HTML print template file must exist alongside the report."""
        import master_plan_it.master_plan_it.report.mpit_overview as _mod_pkg
        report_dir = os.path.dirname(os.path.abspath(_mod_pkg.__file__))
        html_path = os.path.join(report_dir, "mpit_overview.html")
        self.assertTrue(os.path.isfile(html_path), f"Missing print template: {html_path}")

    def test_js_has_print_filters(self):
        """The .js filter file must declare print_profile, print_orientation, print_density."""
        import master_plan_it.master_plan_it.report.mpit_overview as _mod_pkg
        report_dir = os.path.dirname(os.path.abspath(_mod_pkg.__file__))
        js_path = os.path.join(report_dir, "mpit_overview.js")
        with open(js_path) as fh:
            js_src = fh.read()
        for fname in ("print_profile", "print_orientation", "print_density"):
            self.assertIn(fname, js_src, f"Missing print filter in JS: {fname}")

    def test_js_year_filter_has_default(self):
        import master_plan_it.master_plan_it.report.mpit_overview as _mod_pkg

        report_dir = os.path.dirname(os.path.abspath(_mod_pkg.__file__))
        js_path = os.path.join(report_dir, "mpit_overview.js")
        with open(js_path) as fh:
            js_src = fh.read()

        self.assertIn('fieldname: "year"', js_src)
        self.assertIn("default: String(new Date().getFullYear())", js_src)

    def test_html_print_template_uses_business_pdf_sections(self):
        import master_plan_it.master_plan_it.report.mpit_overview as _mod_pkg

        report_dir = os.path.dirname(os.path.abspath(_mod_pkg.__file__))
        html_path = os.path.join(report_dir, "mpit_overview.html")
        with open(html_path) as fh:
            html_src = fh.read()

        for expected in (
            "IT Economic Overview",
            "Budget approval note",
            "Financial Legend",
            "Meeting approval",
            "Approved by",
            "Approval date",
            "Signature",
        ):
            self.assertIn(expected, html_src)

    def test_html_print_standard_profile_includes_plafond_consumed(self):
        import master_plan_it.master_plan_it.report.mpit_overview as _mod_pkg

        report_dir = os.path.dirname(os.path.abspath(_mod_pkg.__file__))
        html_path = os.path.join(report_dir, "mpit_overview.html")
        with open(html_path) as fh:
            html_src = fh.read()

        self.assertIn('"plafond_consumed"', html_src)
        self.assertIn('field === "plafond_consumed"', html_src)

    def test_html_print_standard_profile_uses_compact_pdf_labels(self):
        import master_plan_it.master_plan_it.report.mpit_overview as _mod_pkg

        report_dir = os.path.dirname(os.path.abspath(_mod_pkg.__file__))
        html_path = os.path.join(report_dir, "mpit_overview.html")
        with open(html_path) as fh:
            html_src = fh.read()

        self.assertIn("printColumnLabel", html_src)
        self.assertIn('"actual_total": __("Effettivo")', html_src)
        self.assertIn('"forecast_total": __("Previsto")', html_src)
        self.assertIn('"plafond_consumed": __("Consumato")', html_src)
        self.assertIn("PDF headers are intentionally shorter", html_src)

    def test_html_print_standard_profile_excludes_ideas_to_prevent_pdf_clipping(self):
        import master_plan_it.master_plan_it.report.mpit_overview as _mod_pkg

        report_dir = os.path.dirname(os.path.abspath(_mod_pkg.__file__))
        html_path = os.path.join(report_dir, "mpit_overview.html")
        with open(html_path) as fh:
            html_src = fh.read()

        match = re.search(r"Standard:\s*\[(.*?)\]", html_src, re.DOTALL)
        self.assertIsNotNone(match)
        standard_block = match.group(1)
        self.assertNotIn('"ideas"', standard_block)
        self.assertIn('"plafond_consumed"', standard_block)

    def test_html_print_template_avoids_line_comments_in_microtemplate(self):
        import master_plan_it.master_plan_it.report.mpit_overview as _mod_pkg

        report_dir = os.path.dirname(os.path.abspath(_mod_pkg.__file__))
        html_path = os.path.join(report_dir, "mpit_overview.html")
        with open(html_path) as fh:
            html_src = fh.read()

        self.assertNotIn("// Print profiles define", html_src)

    def test_html_print_template_maps_technical_filter_values(self):
        import master_plan_it.master_plan_it.report.mpit_overview as _mod_pkg

        report_dir = os.path.dirname(os.path.abspath(_mod_pkg.__file__))
        html_path = os.path.join(report_dir, "mpit_overview.html")
        with open(html_path) as fh:
            html_src = fh.read()

        for expected in (
            '"Summary": __("Executive Summary")',
            '"Build-up": __("Budget Build-up")',
            '"Lines": __("Detailed Lines")',
            '"Actual with Estimates and Quotes": __("Actual Spend with Estimates and Quotes")',
        ):
            self.assertIn(expected, html_src)


class TestCrossCostCenterPlafondOverview(FrappeTestCase):
    def setUp(self):
        frappe.set_user("Administrator")
        self.suffix = uuid4().hex[:6].upper()
        self.year = ensure_year(2030)
        self.cc_funding = ensure_cost_center(f"CC-FUNDING-{self.suffix}")
        self.cc_infra = ensure_cost_center(f"CC-INFRA-{self.suffix}")
        self.vendor = ensure_vendor(f"Vendor Cross {self.suffix}")

        plafond = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Plafond Cross {self.suffix}",
                "expense_kind": "Plafond",
                "year": self.year,
                "cost_center": self.cc_funding,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Funding budget",
                        "row_state": "Active",
                        "amount": 1000,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "spend_date": "2030-01-10",
                    }
                ],
            }
        )
        plafond.insert()

        expense = frappe.get_doc(
            {
                "doctype": "MPIT Expense",
                "expense_title": f"Plafond Consume {self.suffix}",
                "expense_kind": "Ordinary",
                "year": self.year,
                "cost_center": self.cc_infra,
                "uses_plafond": 1,
                "is_extra": 0,
                "plafond_expense": plafond.name,
                "rows": [
                    {
                        "doctype": "MPIT Expense Row",
                        "row_description": "Infra actual",
                        "row_phase": "Actual",
                        "row_state": "Active",
                        "vendor": self.vendor,
                        "amount": 250,
                        "amount_includes_vat": 0,
                        "vat_rate": 22,
                        "spend_date": "2030-02-10",
                    }
                ],
            }
        )
        expense.insert()

    def test_overview_summary_exposes_plafond_consumed(self):
        columns, data, *_ = run_overview({"year": self.year, "view_mode": "Summary", "show_zero_rows": 1})

        fieldnames = {c["fieldname"] for c in columns}
        self.assertIn("plafond_consumed", fieldnames)

        rows_by_cc = {row.get("cost_center"): row for row in data}
        funding = rows_by_cc[self.cc_funding]
        infra = rows_by_cc[self.cc_infra]

        self.assertEqual(funding.get("plafond"), 1000)
        self.assertEqual(funding.get("plafond_consumed"), 250)
        self.assertEqual(funding.get("remaining"), 750)
        self.assertEqual(funding.get("actual_on_plafond"), 0)
        self.assertEqual(funding.get("actual_total"), 0)
        self.assertEqual(infra.get("plafond"), 0)
        self.assertEqual(infra.get("plafond_consumed"), 0)
        self.assertEqual(infra.get("actual_on_plafond"), 250)
        self.assertEqual(infra.get("actual_total"), 250)

    def test_plafond_usage_chart_uses_plafond_consumed(self):
        chart = run_plafond_usage_by_cost_center_chart({"year": self.year})
        labels = chart.get("labels", [])
        datasets = {ds.get("name"): ds.get("values", []) for ds in chart.get("datasets", [])}

        consumed_values = datasets.get("Consumed") or datasets.get("Consumato")
        self.assertIsNotNone(consumed_values)
        self.assertIn(self.cc_funding, labels)
        self.assertIn(self.cc_infra, labels)

        funding_index = labels.index(self.cc_funding)
        infra_index = labels.index(self.cc_infra)

        self.assertEqual(consumed_values[funding_index], 250)
        self.assertEqual(consumed_values[infra_index], 0)


# ---------------------------------------------------------------------------
# Shared fixture helpers
# ---------------------------------------------------------------------------

def ensure_year(year: int) -> str:
    if frappe.db.exists("MPIT Year", str(year)):
        return str(year)

    doc = frappe.get_doc(
        {
            "doctype": "MPIT Year",
            "year": year,
            "start_date": f"{year}-01-01",
            "end_date": f"{year}-12-31",
        }
    )
    doc.insert(ignore_permissions=True)
    return doc.name


def ensure_cost_center(name: str) -> str:
    if frappe.db.exists("MPIT Cost Center", name):
        return name

    doc = frappe.get_doc(
        {
            "doctype": "MPIT Cost Center",
            "cost_center_name": name,
            "is_group": 0,
            "parent_mpit_cost_center": "All Cost Centers",
        }
    )
    doc.insert(ignore_permissions=True)
    return doc.name


def ensure_project(title: str, cost_center: str) -> str:
    existing = frappe.db.get_value("MPIT Project", {"title": title}, "name")
    if existing:
        return existing

    doc = frappe.get_doc(
        {
            "doctype": "MPIT Project",
            "title": title,
            "workflow_state": "Approved",
            "cost_center": cost_center,
        }
    )
    doc.insert(ignore_permissions=True)
    return doc.name


def ensure_vendor(name: str) -> str:
    if frappe.db.exists("MPIT Vendor", name):
        return name

    doc = frappe.get_doc({"doctype": "MPIT Vendor", "vendor_name": name})
    doc.insert(ignore_permissions=True)
    return doc.name


def _insert_contract(
    name_hint: str,
    cost_center: str,
    vendor: str,
    start_date: str = "2032-01-01",
    end_date: str = "2032-12-31",
    terms: list | None = None,
) -> str:
    doc = frappe.get_doc(
        {
            "doctype": "MPIT Contract",
            "description": name_hint,
            "vendor": vendor,
            "cost_center": cost_center,
        }
    )
    if terms:
        doc.terms = []
        for t in terms:
            doc.append(
                "terms",
                {
                    "doctype": "MPIT Contract Term",
                    "from_date": t["from_date"],
                    "to_date": t.get("to_date"),
                    "amount": t["amount"],
                    "amount_net": t.get("amount_net", t["amount"]),
                    "billing_cycle": t.get("billing_cycle", "Monthly"),
                },
            )
    doc.insert(ignore_permissions=True)
    return doc.name
